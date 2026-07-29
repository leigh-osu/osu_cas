#Installation Using Composer (recommended)

If you use Composer to manage dependencies, edit your site's "composer.json"
file as follows.

1. Add the asset-packagist composer repository to "composer.json".
This allows installing packages (like Apache Echarts) that are published on NPM.

        "asset-packagist": {
            "type": "composer",
            "url": "https://asset-packagist.org"
        },

You may need to add it in your composer.json file like this (second item in
the array):

        "repositories": [
            {
                "type": "composer",
                "url": "https://packages.drupal.org/8"
            },
            {
                "type": "composer",
                "url": "https://asset-packagist.org"
            },
        ],

2. Run the following command to ensure that you have the
"oomphinc/composer-installers-extender" package installed. This package
facilitates the installation of any package into directories other than the
default "/vendor" (e.g. "/libraries") using Composer.

        composer require --prefer-dist oomphinc/composer-installers-extender

3. Configure composer to install the Apache Echarts dependencies into
"/libraries" by adding the following "installer-types" and "installer-paths"
to the "extra" section of "composer.json". If you are not using the "web"
directory, then remove "web/" from the lines below:

        "extra": {
            "installer-types": ["npm-asset"],
            "installer-paths": {
                ...
                "web/libraries/{$name}": ["type:drupal-library", "vendor:npm-asset"]
            },
        }

4. This and the following step is optional but recommended. The reason for
them is that when installing the Apache ECharts package with Composer,
additional files are added into the library directory. These files are not
necessary and can be potentially harmful to your site, so it's best to remove
them. So: create a new directory in your project root called "scripts".

5. Inside that directory, create a new file called "clean-echarts.sh" and
   paste the following into it:

        #!/usr/bin/env bash
        set -eu
        declare -a directories=(
          "web/libraries/echarts/asset"
          "web/libraries/echarts/build"
          "web/libraries/echarts/dist/extension"
          "web/libraries/echarts/extension"
          "web/libraries/echarts/extension-src"
          "web/libraries/echarts/i18n"
          "web/libraries/echarts/lib"
          "web/libraries/echarts/licenses"
          "web/libraries/echarts/ssr"
          "web/libraries/echarts/theme"
          "web/libraries/echarts/types"
        )
        counter=0
        echo "Deleting unneeded directories inside web/libraries/echarts"
        for directory in "${directories[@]}"
          do
            if [ -d $directory ]; then
              echo "Deleting $directory"
              rm -rf $directory
              counter=$((counter+1))
            fi
          done
        echo "$counter folders were deleted"
        declare -a files=(
          "web/libraries/echarts/charts.d.ts"
          "web/libraries/echarts/charts.js"
          "web/libraries/echarts/components.d.ts"
          "web/libraries/echarts/components.js"
          "web/libraries/echarts/core.d.ts"
          "web/libraries/echarts/core.js"
          "web/libraries/echarts/features.d.ts"
          "web/libraries/echarts/features.js"
          "web/libraries/echarts/index.blank.js"
          "web/libraries/echarts/index.common.js"
          "web/libraries/echarts/index.d.ts"
          "web/libraries/echarts/index.js"
          "web/libraries/echarts/index.simple.js"
          "web/libraries/echarts/KEYS"
          "web/libraries/echarts/LICENSE"
          "web/libraries/echarts/NOTICE"
          "web/libraries/echarts/package.json"
          "web/libraries/echarts/package.README.md"
          "web/libraries/echarts/README.md"
          "web/libraries/echarts/renderers.d.ts"
          "web/libraries/echarts/renderers.js"
          "web/libraries/echarts/tsconfig.json"
        )
        counter=0
        echo "Deleting unneeded files inside web/libraries/echarts"
        for file in "${files[@]}"
          do
            if [[ -f $file ]]; then
              echo "Deleting $file"
              rm $file
              counter=$((counter+1))
            fi
          done
        echo "$counter files were deleted"

6. Add a "scripts" entry to your "composer.json" file as shown below. If
   "scripts" already exists, you will need to do a little more to incorporate
   the code below.

        "scripts": {
            "clean-echarts": "chmod +x scripts/clean-echarts.sh &&
            ./scripts/clean-echarts.sh",
            "post-install-cmd": [
              "@clean-echarts"
            ],
            "post-update-cmd": [
              "@clean-echarts"
            ]
        }

7. Run the following command; you should find that new directories have been
   created under "/libraries".

        composer require --prefer-dist npm-asset/echarts:^5.5
