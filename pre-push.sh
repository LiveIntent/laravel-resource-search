#!/usr/bin/env bash

end="\033[0m"
function blue { echo -e >&2 "\033[1;34m$@${end}"; }
function lightblue { echo -e >&2 "\033[0;36m$@${end}"; }
function green { echo -e >&2 "\033[1;32m$@${end}"; }
function red { echo -e >&2 "\033[1;31m$@${end}"; }

function onExit {
  echo "> Please fix the errors above and try again."
  echo "> If you absolutely must, you can ignore these checks by pushing again with the --no-verify flag."
}

trap onExit EXIT

blue "> Running pre-push hooks..."

echo
lightblue "> Formatting code..."
composer lint -- --test

if [[ "$?" -ne 0 ]]; then
    echo
    red "> Found files that still need linting."
    echo "> Run \`composer lint\` to fix these formatting issues then try again."
    echo
    exit 1
fi

echo
lightblue "> Checking for potential errors..."
composer analyze

if [[ "$?" -ne 0 ]]; then
    echo
    red "> Found files with code issues."
    echo
    exit 1
fi

echo
lightblue "> Testing..."
composer test

if [[ "$?" -ne 0 ]]; then
    echo
    red "> Not all the tests are passing."
    echo
    exit 1
fi

echo
green "> Code looks good! Pushing..."
echo
