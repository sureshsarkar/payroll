<?php

namespace Modules\Language\app\Enums;

enum TranslationModels: string
{
    /**
     * whenever update new case also update getAll() method
     * to return all values in array
     *
     * LMS removal phase 2 (2026-08-27) — every case pointed at a translation
     * model inside a deleted module (Blog, Testimonial, Faq, Course, Frontend,
     * Menubuilder, PageBuilder, InstructorRequest), and every caller of this
     * enum lived in those same modules. Nothing in the HR/Payroll product is
     * translatable through this mechanism yet, so the enum is deliberately left
     * empty rather than deleted: GenerateTranslationTrait and
     * TranslateableModelsTrait still type-hint it, and adding a case back is
     * the whole job when HR gets translatable content.
     */
    public static function getAll(): array
    {
        return [];
    }

    public static function igonreColumns(): array
    {
        return [
            'id',
            'lang_code',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }
}
