#!/bin/zsh


# add --delete for mirroring

# Public files → Acquia dev
rsync -avz --progress --partial --timeout=120 \
  -e "ssh -o ServerAliveInterval=30 -o ServerAliveCountMax=10 -o TCPKeepAlive=yes" \
  /Users/leighr/Sites/osu/osu_cas/docroot/sites/agsci.oregonstate.edu/files/ \
  osucas.dev@osucasdev.ssh.prod.acquia-sites.com:/mnt/files/osucas.dev/sites/agsci.oregonstate.edu/files/

# Private files → Acquia dev
rsync -avz --progress --partial --timeout=120 \
  -e "ssh -o ServerAliveInterval=30 -o ServerAliveCountMax=10 -o TCPKeepAlive=yes" \
  /Users/leighr/Sites/osu/osu_cas/files/agsci/private-files/ \
  osucas.dev@osucasdev.ssh.prod.acquia-sites.com:/mnt/files/osucas.dev/sites/agsci.oregonstate.edu/files-private/

