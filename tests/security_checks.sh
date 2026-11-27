#!/bin/sh
set -eu

cd "$(dirname "$0")/.."

echo "Checking that no environment file is present..."
test ! -f .env

echo "Checking that placeholder secrets are not used by public JavaScript..."
if grep -R -E "SUPABASE_(ANON|SERVICE_ROLE)_KEY|service_role" public --include='*.js' --include='*.html'; then
  echo "Secret-related configuration was found in public browser assets."
  exit 1
fi

echo "Checking protected PHP pages call require_auth..."
for page in \
  public/dashboard.php public/profile.php \
  public/sows/index.php public/sows/create.php public/sows/edit.php public/sows/view.php \
  public/breeding/index.php public/breeding/create.php public/breeding/edit.php \
  public/care/index.php public/care/create.php public/care/edit.php \
  public/schedule/index.php
do
  grep -q "require_auth();" "$page" || {
    echo "Missing require_auth in $page"
    exit 1
  }
done

echo "Static security checks passed."
