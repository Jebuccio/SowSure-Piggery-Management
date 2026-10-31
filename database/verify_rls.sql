-- Read-only verification queries. Run after schema.sql and policies.sql.

select schemaname, tablename, rowsecurity
from pg_tables
where schemaname = 'public'
  and tablename in ('profiles', 'sows', 'breeding_records', 'care_activities')
order by tablename;

select schemaname, tablename, policyname, roles, cmd, qual, with_check
from pg_policies
where schemaname = 'public'
  and tablename in ('profiles', 'sows', 'breeding_records', 'care_activities')
order by tablename, cmd;

select trigger_name, event_object_schema, event_object_table, action_timing, event_manipulation
from information_schema.triggers
where event_object_schema in ('public', 'auth')
  and trigger_name in (
    'on_auth_user_created',
    'breeding_owner_guard',
    'care_owner_guard',
    'care_future_date_guard'
  )
order by event_object_schema, event_object_table, trigger_name;
