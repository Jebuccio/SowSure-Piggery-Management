<?php
declare(strict_types=1);

function find_owned_sow(string $id, bool $includeArchived = false): ?array
{
    global $supabase;
    if (!is_uuid($id)) {
        return null;
    }
    $query = [
        'select' => 'id,name,tag_number,status,notes,created_at,updated_at,archived_at',
        'id' => 'eq.' . $id,
        'owner_id' => 'eq.' . auth_user_id(),
        'limit' => '1',
    ];
    if (!$includeArchived) {
        $query['archived_at'] = 'is.null';
    }
    $rows = $supabase->select('sows', $query, access_token());
    return $rows[0] ?? null;
}

function owned_active_sows(): array
{
    global $supabase;
    return $supabase->select('sows', [
        'select' => 'id,name,tag_number,status',
        'owner_id' => 'eq.' . auth_user_id(),
        'archived_at' => 'is.null',
        'order' => 'name.asc',
    ], access_token());
}

function find_owned_record(string $table, string $id, string $select = '*'): ?array
{
    global $supabase;
    if (!is_uuid($id) || !in_array($table, ['breeding_records', 'care_activities'], true)) {
        return null;
    }
    $rows = $supabase->select($table, [
        'select' => $select,
        'id' => 'eq.' . $id,
        'owner_id' => 'eq.' . auth_user_id(),
        'limit' => '1',
    ], access_token());
    return $rows[0] ?? null;
}
