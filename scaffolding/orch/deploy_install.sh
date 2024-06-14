#!/usr/bin/env bash

set -e

./orch/show_file.sh $0

# Normally, XDEBUG_MODE=debug,develop but develop breaks the Drupal installation.
# https://www.drupal.org/project/drupal/issues/3405976.
if [ -n "$XDEBUG_MODE" ]; then
  export XDEBUG_MODE=debug
fi

drush cr

# If using Postgres, enable the pg_trgm extension which is required before
# Drupal is installed.
if [ -n "$(drush status | grep pgsql 2>/dev/null)" ]; then
  echo 'Postgres is installed, enabling the pg_trgm extension.'
  drush sql:query 'CREATE EXTENSION IF NOT EXISTS pg_trgm;'
fi

# Prompts the user for installation profile to use.
drupal_profile() {
  # If on the remote, don't prompt, just use minimal. This should never be hit because they should have exported config
  # before running on the remote...but you never know.
  if [ -z "$DRUPAL_ENV_REMOTE" ]; then
    # Prompt the user for a string value
    read -p "Which installation profile would you like to install? 'standard' and 'minimal' are available in core, you must install others yourself before continuing. Press enter for 'minimal'.): " profile
  fi

  # Default to 'minimal' if no value is provided
  if [ -z "$profile" ]; then
    profile="minimal"
  fi

  echo "$profile"
}

if [ -n "$(ls $(drush php:eval "echo realpath(Drupal\Core\Site\Settings::get('config_sync_directory'));")/*.yml 2>/dev/null)" ]; then
  # Find the profile in config.
  PROFILE=$(grep 'profile:' config/sync/core.extension.yml 2>/dev/null | awk '{print $2}')
  # Check if 'grep' found a match
  if [ -z "$PROFILE" ]; then
      # Set default value to 'minimal'
      PROFILE=$(drupal_profile)
  fi
  echo "Installing a fresh Drupal site from configuration"
  drush si -y --account-pass='admin' --existing-config ${PROFILE}
  # Required if config splits is enabled.
  if drush pm-list --type=module --status=enabled --no-core | grep 'config_split'; then
    drush cr
    drush cim -y
  fi
else
  echo "Installing a fresh Drupal site without configuration"
  drush si -y --account-pass='admin' $(drupal_profile)
fi

# Clear cache after installation
drush cr

./orch/show_file.sh $0 end
