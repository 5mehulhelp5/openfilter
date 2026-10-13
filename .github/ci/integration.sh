#!/usr/bin/env bash
# Integration tests against a real search engine. OpenFilter lives in the search adapter, so the ExtDN action
# (which wires only Elasticsearch 8) is not enough: this script builds the Magento project from the public mirror,
# adds this checkout as a path package, points the test framework at OpenSearch (or Elasticsearch 8 on 2.4.7)
# and runs Test/Integration. MySQL and the search engine are started by the workflow.
#
# Env: MAGENTO_VERSION (e.g. 2.4.9), SEARCH_ENGINE (opensearch | elasticsearch8), MODULE_DIR (this checkout).
set -euo pipefail

: "${MAGENTO_VERSION:?}" "${SEARCH_ENGINE:=opensearch}" "${MODULE_DIR:?}" "${MAGENTO_ROOT:=/tmp/magento}"
MIRROR=https://repo-magento-mirror.fooman.co.nz/
package="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["name"];' "$MODULE_DIR/composer.json")"

echo "::group::Composer project $MAGENTO_VERSION"
if [ -n "${GITHUB_TOKEN:-}" ]; then composer config -g github-oauth.github.com "$GITHUB_TOKEN"; fi
composer create-project --no-interaction --no-progress --no-install --repository-url="$MIRROR" \
  "magento/project-community-edition=$MAGENTO_VERSION" "$MAGENTO_ROOT"
cd "$MAGENTO_ROOT"
php -r '$f = "composer.json"; $c = json_decode(file_get_contents($f), true);
  $c["repositories"] = ["magento-mirror" => ["type" => "composer", "url" => $argv[1]]];
  file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");' "$MIRROR"
composer config repositories.module '{"type":"path","url":"'"$MODULE_DIR"'","options":{"symlink":false,"versions":{"'"$package"'":"1.0.0"}}}'
# Extra path packages (e.g. openfilter for the openfilter-hyva job): space-separated "name=dir" pairs.
for extra in ${EXTRA_PATH_PACKAGES:-}; do
  name="${extra%%=*}"; dir="${extra#*=}"
  composer config "repositories.${name//\//-}" '{"type":"path","url":"'"$dir"'","options":{"symlink":false,"versions":{"'"$name"'":"1.0.0"}}}'
done
composer config --no-plugins allow-plugins.magento/composer-dependency-version-audit-plugin false
composer config --no-plugins allow-plugins.magento/* true
composer config --no-plugins allow-plugins.laminas/laminas-dependency-plugin true
composer config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-plugins allow-plugins.php-http/discovery true
composer require --no-update --no-interaction "$package:1.0.0"
composer install --no-interaction --no-progress --prefer-dist \
  || { echo "composer install failed, retrying once"; composer clear-cache; composer install --no-interaction --no-progress --prefer-dist; }
echo "::endgroup::"

echo "::group::Test framework configuration ($SEARCH_ENGINE)"
cd dev/tests/integration
php -r '
  $c = require "etc/install-config-mysql.php.dist";
  foreach (array_keys($c) as $k) {
      if (preg_match("/^(amqp|opensearch|elasticsearch|consumers-wait)/", $k)) { unset($c[$k]); }
  }
  $c["db-host"] = "127.0.0.1"; $c["db-user"] = "root"; $c["db-password"] = "root";
  $c["db-name"] = "magento_integration_tests";
  $engine = $argv[1];
  $c["search-engine"] = $engine;
  $prefix = $engine === "opensearch" ? "opensearch" : "elasticsearch";
  $c[$prefix . "-host"] = "127.0.0.1"; $c[$prefix . "-port"] = 9200;
  file_put_contents("etc/install-config-mysql.php", "<?php\nreturn " . var_export($c, true) . ";\n");
' "$SEARCH_ENGINE"
cat etc/install-config-mysql.php
cp phpunit.xml.dist phpunit.xml
echo "::endgroup::"

echo "::group::Integration tests"
php -d memory_limit=-1 ../../../vendor/bin/phpunit -c phpunit.xml "../../../vendor/$package/Test/Integration"
echo "::endgroup::"
