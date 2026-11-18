<?php

namespace App\Support;

/**
 * Every permission the app checks, and the default roles.
 * Roles/permissions can be changed later in User management → Roles.
 */
class Permissions
{
    protected const CRUD = ['access', 'create', 'edit', 'show', 'delete'];

    public static function all(): array
    {
        $list = ['user_management_access'];

        foreach (['permission', 'role', 'user', 'user_info'] as $p) {
            foreach (self::CRUD as $a) {
                $list[] = "{$p}_{$a}";
            }
        }
        array_push($list,
            'audit_log_access', 'audit_log_show',
            'user_log_access', 'user_log_delete',
            'user_alert_access', 'user_alert_create', 'user_alert_show', 'user_alert_delete',
            'setting_access',
        );
        foreach (self::settings() as $p) {
            foreach (self::CRUD as $a) {
                $list[] = "{$p}_{$a}";
            }
        }
        array_push($list,
            // documents
            'document_access', 'document_create', 'document_edit', 'document_show', 'document_delete',
            'document_delete_all',      // bulk delete
            'document_print_access',
            'document_grand_access',    // see every document, not only the ones you take part in
            'document_approve_access',  // can be chosen as the Boss (final approval & e-signature)
            'document_complete_access', // see the Closed tab
            'document_complete_edit',   // close / reopen documents
            // comments
            'comment_access', 'comment_create', 'comment_edit', 'comment_show', 'comment_delete',
            'comment_grand_access',     // see every comment
            'comment_grand_edit',       // add / edit any comment, re-open steps
            'comment_status_view',      // see who is waiting / whose turn it is
            'profile_password_edit',
        );

        return $list;
    }

    /** Settings resource => permission prefix */
    public static function settings(): array
    {
        return [
            'departments' => 'department',
            'titles' => 'title',
            'positions' => 'position',
            'categories' => 'category',
            'organisations' => 'organisation',
            'document-types' => 'document_type',
            'staff' => 'staff',
            'app-settings' => 'app_setting',
        ];
    }

    /** Default roles (created once; edit them in the Roles screen). */
    public static function defaultRoles(): array
    {
        $documentBasics = ['document_access', 'document_show', 'profile_password_edit', 'comment_status_view'];
        $commenting = ['comment_access', 'comment_create', 'comment_edit', 'comment_show'];
        $docTypes = array_map(fn ($a) => "document_type_{$a}", self::CRUD);
        $registering = ['document_create', 'document_edit', 'document_delete', 'document_complete_access', 'document_complete_edit', 'document_print_access'];

        return [
            'Admin' => self::all(),
            'Direction' => array_merge($documentBasics, $commenting, $registering, $docTypes, [
                'document_grand_access', 'document_approve_access', 'comment_delete', 'comment_grand_access',
                'organisation_access', 'organisation_show',
            ]),
            'Moderator' => array_merge($documentBasics, $commenting, $registering, $docTypes, [
                'document_grand_access', 'document_delete_all', 'comment_delete', 'comment_grand_access', 'comment_grand_edit',
                'setting_access',
            ]),
            'Creator' => array_merge($documentBasics, ['comment_access', 'comment_show'], $registering),
            'Document Print' => array_merge($documentBasics, ['document_print_access']),
            'User' => array_merge(['document_access', 'document_show', 'profile_password_edit'], $commenting),
        ];
    }
}
