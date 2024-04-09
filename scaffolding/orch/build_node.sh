#!/usr/bin/env bash

set -e

./orch/show_file.sh $0

green='\033[0;32m'
NC='\033[0m'
echo "Front end can be built by using the file ./orch/build_node.sh"

# find all custom themes that contain a package.json file
directories=$(find web/themes/custom -maxdepth 2 -type f -name 'package.json' -exec dirname {} \;)

# Loop through each directory found
for dir in $directories; do
    echo "Processing directory: $dir"
    # Run in sub-shell so CWD is preserved.
    (
      cd $dir

      echo -e "${green}Installing NPMs${NC}"
      npm install --prefer-offline

      echo -e "${yellow}Gulp Build${NC}"
      gulp
    )
done

./orch/show_file.sh $0 end
