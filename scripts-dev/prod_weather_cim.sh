#!/bin/bash
# Partial-import the six weather config items on prod (deploy hooks don't cim).
# Run from the repo root:  bash scripts-dev/prod_weather_cim.sh
set -euo pipefail
ssh osucas.prod@osucasprod.ssh.prod.acquia-sites.com bash -s <<'EOF'
set -euo pipefail
cd /var/www/html
C=config/agsci.oregonstate.edu
D=$HOME/weathercfg
rm -rf "$D"; mkdir -p "$D"
cp "$C"/auto_entitylabel.settings.node.weather_daily_data.yml \
   "$C"/auto_entitylabel.settings.node.weather_monthly_data.yml \
   "$C"/core.entity_form_display.node.weather_daily_data.default.yml \
   "$C"/core.entity_form_display.node.weather_monthly_data.default.yml \
   "$C"/field.field.node.weather_daily_data.field_dw_location.yml \
   "$C"/field.field.node.weather_monthly_data.field_mw_location.yml \
   "$D"/
echo "staged $(ls "$D" | wc -l) files in $D"
./vendor/bin/drush --uri=agsci.oregonstate.edu cim --partial --source="$D" -y
./vendor/bin/drush --uri=agsci.oregonstate.edu cr
echo "monthly auto-title status: $(./vendor/bin/drush --uri=agsci.oregonstate.edu cget auto_entitylabel.settings.node.weather_monthly_data status 2>/dev/null)"
echo "daily location required:   $(./vendor/bin/drush --uri=agsci.oregonstate.edu cget field.field.node.weather_daily_data.field_dw_location required 2>/dev/null)"
EOF
