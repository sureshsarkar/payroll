<?php

use Modules\Menubuilder\app\Models\Menus;
use Modules\Menubuilder\app\Models\MenuItem;


if (! function_exists('menu_get_by_slug')) {
    function menu_get_by_slug($slug){
        return Menus::select('id')->with('menuItems')->whereSlug($slug)->first();
    }
}
if (! function_exists('currectUrlWithQuery')) {
    function currectUrlWithQuery($code){
        $currentUrlWithQuery = request()->fullUrl();

        // Parse the query string
        $parsedQuery = parse_url($currentUrlWithQuery, PHP_URL_QUERY);

        // Check if the 'code' parameter already exists
        $codeExists = false;
        if ($parsedQuery) {
            parse_str($parsedQuery, $queryArray);
            $codeExists = isset($queryArray['code']);
        }

        if ($codeExists) {
            $updatedUrlWithQuery = preg_replace('/(\?|&)code=[^&]*/', '$1code=' . $code, $currentUrlWithQuery);
        } else {
            $updatedUrlWithQuery = $currentUrlWithQuery . ($parsedQuery ? '&' : '?') . http_build_query(['code' => $code]);
        }
        return $updatedUrlWithQuery;
    }
}