<?php

namespace Modules\GlobalSetting\app\Traits;

use ZipArchive;

trait ArchiveHelperTrait
{
    private function isFirstDirAddons($zipFilePath)
    {
        $zip = new ZipArchive;
        if ($zip->open($zipFilePath) === true) {
            $firstDir = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileInfo = $zip->statIndex($i);
                $filePathParts = explode('/', $fileInfo['name']);

                if (count($filePathParts) > 1) {
                    $firstDir = $filePathParts[0];
                    break;
                }
            }

            $zip->close();

            return $firstDir === 'Modules';
        }

        $zip->close();

        return false;
    }

    private function checkAndReadJsonFile($zipPath, $searchFile = 'wsus.json')
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $found = false;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                $pathInfo = explode('/', $entry);
                if (count($pathInfo) === 3 && $pathInfo[0] === 'Modules') {
                    if (strpos($entry, $searchFile) !== false) {
                        $found = true;
                        $wsusJsonContent = $zip->getFromName($entry);

                        if (json_decode($wsusJsonContent) !== null) {
                            return json_decode($wsusJsonContent);
                        } else {
                            return false;
                        }
                        break;
                    }
                }
            }
            if (! $found) {
                return false;
            }
            $zip->close();
        }

        return false;
    }

    private function deleteFolderAndFiles($dir)
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir.'/'.$file;

            if (is_dir($path)) {
                $this->deleteFolderAndFiles($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
