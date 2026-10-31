-- SowSure Row Level Security policies
-- Run after database/schema.sql.

alter table public.profiles enable row level security;
alter table public.sows enable row level security;
alter table public.breeding_records enable row level security;
alter table public.care_activities enable row level security;

drop policy if exists "profiles_select_own" on public.profiles;
create policy "profiles_select_own" on public.profiles
for select to authenticated using (id = auth.uid());

drop policy if exists "profiles_update_own" on public.profiles;
create policy "profiles_update_own" on public.profiles
for update to authenticated using (id = auth.uid()) with check (id = auth.uid());

drop policy if exists "sows_select_own" on public.sows;
create policy "sows_select_own" on public.sows
for select to authenticated using (owner_id = auth.uid());
drop policy if exists "sows_insert_own" on public.sows;
create policy "sows_insert_own" on public.sows
for insert to authenticated with check (owner_id = auth.uid());
drop policy if exists "sows_update_own" on public.sows;
create policy "sows_update_own" on public.sows
for update to authenticated using (owner_id = auth.uid()) with check (owner_id = auth.uid());

drop policy if exists "breeding_select_own" on public.breeding_records;
create policy "breeding_select_own" on public.breeding_records
for select to authenticated using (
    owner_id = auth.uid()
    and exists (select 1 from public.sows s where s.id = sow_id and s.owner_id = auth.uid())
);
drop policy if exists "breeding_insert_own" on public.breeding_records;
create policy "breeding_insert_own" on public.breeding_records
for insert to authenticated with check (
    owner_id = auth.uid()
    and exists (select 1 from public.sows s where s.id = sow_id and s.owner_id = auth.uid())
);
drop policy if exists "breeding_update_own" on public.breeding_records;
create policy "breeding_update_own" on public.breeding_records
for update to authenticated using (
    owner_id = auth.uid()
) with check (
    owner_id = auth.uid()
    and exists (select 1 from public.sows s where s.id = sow_id and s.owner_id = auth.uid())
);

drop policy if exists "care_select_own" on public.care_activities;
create policy "care_select_own" on public.care_activities
for select to authenticated using (
    owner_id = auth.uid()
    and exists (select 1 from public.sows s where s.id = sow_id and s.owner_id = auth.uid())
);
drop policy if exists "care_insert_own" on public.care_activities;
create policy "care_insert_own" on public.care_activities
for insert to authenticated with check (
    owner_id = auth.uid()
    and exists (select 1 from public.sows s where s.id = sow_id and s.owner_id = auth.uid())
);
drop policy if exists "care_update_own" on public.care_activities;
create policy "care_update_own" on public.care_activities
for update to authenticated using (
    owner_id = auth.uid()
) with check (
    owner_id = auth.uid()
    and exists (select 1 from public.sows s where s.id = sow_id and s.owner_id = auth.uid())
);

revoke all on public.profiles, public.sows, public.breeding_records, public.care_activities from anon;
revoke update on public.profiles from authenticated;
grant select on public.profiles to authenticated;
grant update (full_name, farm_name, username, preferred_language) on public.profiles to authenticated;
grant select, insert, update on public.sows, public.breeding_records, public.care_activities to authenticated;

-- Service-role access bypasses RLS by design. It is used only by the PHP
-- server for a single username lookup and must never be exposed publicly.
