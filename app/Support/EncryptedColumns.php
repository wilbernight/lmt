<?php

namespace App\Support;

/**
 * Every column whose contents are encrypted at rest, in one place: the
 * migration that encrypted the existing rows and the re-encryption command
 * (key rotation) both walk this list.
 *
 * Values are encrypted with AES-256-GCM using DATA_ENCRYPTION_KEY, a key kept
 * outside the database. Columns left in plain text on purpose: ids, foreign
 * keys, dates, enums/statuses (needed for filtering), users.name / users.email
 * (sign-in lookups and member lists) and invitation e-mails (lookups).
 */
class EncryptedColumns
{
    /** table => [column => 'string'|'json'] */
    public const MAP = [
        'workspaces' => ['name' => 'string'],
        'projects' => ['name' => 'string', 'description' => 'string'],
        'board_columns' => ['name' => 'string'],
        'tasks' => ['title' => 'string', 'description' => 'string'],
        'checklist_items' => ['body' => 'string'],
        'task_comments' => ['body' => 'string'],
        'task_activities' => ['data' => 'json'],
        'time_entries' => ['note' => 'string'],
        'labels' => ['name' => 'string'],
        'task_categories' => ['name' => 'string'],
        'conversations' => ['name' => 'string'],
        'messages' => ['body' => 'string'],
        'letters' => ['subject' => 'string', 'body' => 'string'],
        'letter_referrals' => ['note' => 'string'],
        'notes' => ['title' => 'string', 'body' => 'string', 'items' => 'json'],
        'customers' => [
            'name' => 'string', 'company' => 'string', 'job_title' => 'string', 'phone' => 'string', 'mobile' => 'string',
            'email' => 'string', 'website' => 'string', 'city' => 'string', 'address' => 'string', 'notes' => 'string',
            'deal_value' => 'string',
        ],
        'customer_interactions' => ['body' => 'string'],
        'attachments' => ['original_name' => 'string'],
        'audit_logs' => ['properties' => 'json'],
        'notifications' => ['data' => 'json'],
        'sms_messages' => ['body' => 'string'],
        'sms_groups' => ['name' => 'string'],
        'users' => ['phone' => 'string'],
    ];

    /** Unique indexes that cannot work on randomised ciphertext; uniqueness is checked in PHP instead. */
    public const DROPPED_UNIQUE = [
        'labels' => ['workspace_id', 'scope', 'name'],
        'task_categories' => ['workspace_id', 'name'],
        'sms_groups' => ['name'],
    ];
}
