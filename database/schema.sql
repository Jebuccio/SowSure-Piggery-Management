-- SowSure schema for Supabase PostgreSQL
-- Run this file first in Supabase Dashboard > SQL Editor.

create extension if not exists pgcrypto;

create table if not exists public.profiles (
    id uuid primary key references auth.users(id) on delete cascade,
    full_name text not null check (char_length(trim(full_name)) between 2 and 120),
    farm_name text not null check (char_length(trim(farm_name)) between 2 and 120),
    username text not null,
    login_email text not null,
    preferred_language text not null default 'en' check (preferred_language in ('en')),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint profiles_username_format check (username ~ '^[a-z0-9][a-z0-9._-]{2,29}$')
);

create unique index if not exists profiles_username_unique
    on public.profiles (lower(username));
create unique index if not exists profiles_login_email_unique
    on public.profiles (lower(login_email));

create table if not exists public.sows (
    id uuid primary key default gen_random_uuid(),
    owner_id uuid not null references public.profiles(id) on delete cascade,
    name text not null check (char_length(trim(name)) between 1 and 100),
    tag_number text not null check (char_length(trim(tag_number)) between 1 and 50),
    status text not null default 'healthy'
        check (status in ('healthy', 'breeding', 'gestating', 'isolated')),
    notes text check (notes is null or char_length(notes) <= 2000),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    archived_at timestamptz
);

-- Tag numbers are unique per owner, not globally across all farms.
create unique index if not exists sows_owner_tag_unique
    on public.sows (owner_id, lower(tag_number));
create index if not exists sows_owner_active_idx
    on public.sows (owner_id, archived_at, status);

create table if not exists public.breeding_records (
    id uuid primary key default gen_random_uuid(),
    sow_id uuid not null references public.sows(id) on delete restrict,
    owner_id uuid not null references public.profiles(id) on delete cascade,
    breeding_date date not null,
    expected_farrowing_date date generated always as (breeding_date + 114) stored,
    notes text check (notes is null or char_length(notes) <= 2000),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists breeding_owner_date_idx
    on public.breeding_records (owner_id, breeding_date desc);
create index if not exists breeding_sow_date_idx
    on public.breeding_records (sow_id, breeding_date desc);

create table if not exists public.care_activities (
    id uuid primary key default gen_random_uuid(),
    sow_id uuid not null references public.sows(id) on delete restrict,
    owner_id uuid not null references public.profiles(id) on delete cascade,
    activity_type text not null
        check (activity_type in ('vaccination', 'vitamins', 'deworming', 'health_check', 'other')),
    scheduled_date date not null,
    notes text check (notes is null or char_length(notes) <= 2000),
    completed_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists care_owner_schedule_idx
    on public.care_activities (owner_id, completed_at, scheduled_date);
create index if not exists care_sow_schedule_idx
    on public.care_activities (sow_id, scheduled_date desc);

create or replace function public.set_updated_at()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

create or replace function public.enforce_child_owner()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
    parent_owner uuid;
begin
    select owner_id into parent_owner from public.sows where id = new.sow_id;
    if parent_owner is null or parent_owner <> new.owner_id then
        raise exception 'The sow does not belong to this owner.' using errcode = '22023';
    end if;
    return new;
end;
$$;

create or replace function public.reject_past_care_date()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
    if (tg_op = 'INSERT' or new.scheduled_date is distinct from old.scheduled_date)
       and new.scheduled_date < (now() at time zone 'Asia/Manila')::date then
        raise exception 'Scheduled care date cannot be earlier than today.' using errcode = '22023';
    end if;
    return new;
end;
$$;

drop trigger if exists profiles_updated_at on public.profiles;
create trigger profiles_updated_at before update on public.profiles
for each row execute function public.set_updated_at();
drop trigger if exists sows_updated_at on public.sows;
create trigger sows_updated_at before update on public.sows
for each row execute function public.set_updated_at();
drop trigger if exists breeding_updated_at on public.breeding_records;
create trigger breeding_updated_at before update on public.breeding_records
for each row execute function public.set_updated_at();
drop trigger if exists care_updated_at on public.care_activities;
create trigger care_updated_at before update on public.care_activities
for each row execute function public.set_updated_at();

drop trigger if exists breeding_owner_guard on public.breeding_records;
create trigger breeding_owner_guard before insert or update on public.breeding_records
for each row execute function public.enforce_child_owner();
drop trigger if exists care_owner_guard on public.care_activities;
create trigger care_owner_guard before insert or update on public.care_activities
for each row execute function public.enforce_child_owner();
drop trigger if exists care_future_date_guard on public.care_activities;
create trigger care_future_date_guard before insert or update on public.care_activities
for each row execute function public.enforce_child_owner(); -- wait, keep standard trigger function reference below

drop trigger if exists care_future_date_guard on public.care_activities;
create trigger care_future_date_guard before insert or update on public.care_activities
for each row execute function public.reject_past_care_date();

-- Enhanced handle_new_user with safe fallbacks for Google OAuth metadata
create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
    _full_name text;
    _farm_name text;
    _username text;
    _email text;
begin
    _email := lower(coalesce(new.email, 'user@example.com'));
    
    _full_name := trim(coalesce(new.raw_user_meta_data ->> 'full_name', new.raw_user_meta_data ->> 'name', split_part(_email, '@', 1), 'Farm User'));
    if length(_full_name) < 2 then
        _full_name := 'Farm User';
    end if;

    _farm_name := trim(coalesce(new.raw_user_meta_data ->> 'farm_name', 'My Farm'));
    if length(_farm_name) < 2 then
        _farm_name := 'My Farm';
    end if;

    _username := lower(trim(coalesce(new.raw_user_meta_data ->> 'username', split_part(_email, '@', 1))));
    _username := regexp_replace(_username, '[^a-z0-9._-]', '', 'g');
    if length(_username) < 3 then
        _username := 'user_' || substr(md5(random()::text), 1, 6);
    end if;
    if length(_username) > 29 then
        _username := substr(_username, 1, 29);
    end if;

    insert into public.profiles (id, full_name, farm_name, username, login_email)
    values (
        new.id,
        _full_name,
        _farm_name,
        _username,
        _email
    )
    on conflict (id) do nothing;
    
    return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
after insert on auth.users
for each row execute function public.handle_new_user();

-- Enable Row Level Security (RLS) on all tables
alter table public.profiles enable row level security;
alter table public.sows enable row level security;
alter table public.breeding_records enable row level security;
alter table public.care_activities enable row level security;

-- Create RLS Policies for secure data isolation
drop policy if exists "Users can view own profile" on public.profiles;
drop policy if exists "Users can insert own profile" on public.profiles;
drop policy if exists "Users can update own profile" on public.profiles;
drop policy if exists "Users can manage own sows" on public.sows;
drop policy if exists "Users can manage own breeding records" on public.breeding_records;
drop policy if exists "Users can manage own care activities" on public.care_activities;

create policy "Users can view own profile" on public.profiles for select using (auth.uid() = id);
create policy "Users can insert own profile" on public.profiles for insert with check (auth.uid() = id);
create policy "Users can update own profile" on public.profiles for update using (auth.uid() = id);

create policy "Users can manage own sows" on public.sows for all using (auth.uid() = owner_id) with check (auth.uid() = owner_id);
create policy "Users can manage own breeding records" on public.breeding_records for all using (auth.uid() = owner_id) with check (auth.uid() = owner_id);
create policy "Users can manage own care activities" on public.care_activities for all using (auth.uid() = owner_id) with check (auth.uid() = owner_id);