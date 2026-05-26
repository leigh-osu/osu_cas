#!/bin/zsh

rsync -avz --progress \
  docroot/sites/agsci.oregonstate.edu/files/ \
  osucas.dev@osucasdev.ssh.prod.acquia-sites.com:/var/www/html/osucas.dev/docroot/sites/agsci.oregonstate.edu/files/
