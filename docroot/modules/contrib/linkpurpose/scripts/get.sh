#!/bin/bash

# This is a simple script to pull down the specified version of editoria11y from github

GIT_REF="main"

mkdir -p tmp/
cd tmp/
git clone git@github.com:itmaybejj/linkpurpose.git .
git checkout $GIT_REF
rm -rf ../library/js
rm -rf ../library/css
mv js ../library/js
mv css ../library/css
cd ../
rm -rf tmp
