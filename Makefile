SW_API_HOSTNAME ?= api.synergywholesale.com
SW_FRONTEND_HOSTNAME ?= manage.synergywholesale.com
RELEASE_DATE := $(shell date '+%A, %B %d %Y')

# Make sure sed replace works on Mac OSX
SED_PARAM := 
ifeq ($(shell uname -s),Darwin)
	SED_PARAM += ''
endif

# In case the version tag isn't annoated, let's have a fallback
VERSION := $(shell git describe --abbrev=0)
ifneq ($(.SHELLSTATUS), 0)
	VERSION := $(shell git describe --tags)
endif

build-assets:
	npm run-script build

update-whois:
	curl -s "https://$(SW_FRONTEND_HOSTNAME)/home/whmcs-whois-json" > "resources/domains/whois.json"

replace:
	sed -i${SED_PARAM} "s/{{VERSION}}/${VERSION}/g" "README.txt"
	sed -i${SED_PARAM} "s/{{RELEASE_DATE}}/${RELEASE_DATE}/g" "README.txt"
	sed -i${SED_PARAM} "s/{{VERSION}}/${VERSION}/g" "modules/registrars/synergywholesaledomains/synergywholesaledomains.php"
	sed -i${SED_PARAM} "s/{{API}}/${SW_API_HOSTNAME}/g" "modules/registrars/synergywholesaledomains/synergywholesaledomains.php"
	sed -i${SED_PARAM} "s/{{FRONTEND}}/${SW_FRONTEND_HOSTNAME}/g" "modules/registrars/synergywholesaledomains/synergywholesaledomains.php"

revert:
	sed -i${SED_PARAM} "s/${VERSION}/{{VERSION}}/g" "README.txt"
	sed -i${SED_PARAM} "s/${RELEASE_DATE}/{{RELEASE_DATE}}/g" "README.txt"
	sed -i${SED_PARAM} "s/${VERSION}/{{VERSION}}/g" "modules/registrars/synergywholesaledomains/synergywholesaledomains.php"
	sed -i${SED_PARAM} "s/${SW_API_HOSTNAME}/{{API}}/g" "modules/registrars/synergywholesaledomains/synergywholesaledomains.php"
	sed -i${SED_PARAM} "s/${SW_FRONTEND_HOSTNAME}/{{FRONTEND}}/g" "modules/registrars/synergywholesaledomains/synergywholesaledomains.php"

package:
	zip -r "synergy-wholesale-domains-$(VERSION).zip" . -x  \
	'.DS_Store' '*.cache' '.git*' '*.md' 'Makefile' 'package.json' 'package-lock.json' \
	'composer.json' 'composer.lock' '*.xml'  '**/synergywholesaledomains.css' '**/functions.js' \
	'vendor/*' 'node_modules/*' '.git/*' 'tests/*'

build:
	make test
	make build-assets
	make update-whois
	make replace
	make package
	make revert

test:
	test -s vendor/bin/phpcs || composer install
	./vendor/bin/phpcs
	./vendor/bin/phpunit
	test -s node_modules/.bin/minify || npm install
	npm run-script check-syntax

tools:
	npm install
	composer install