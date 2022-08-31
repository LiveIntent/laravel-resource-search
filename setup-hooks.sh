#!/bin/bash

hookdir=$(git rev-parse --git-path hooks)

cp pre-push.sh "${hookdir}/pre-push"
chmod ug+x "${hookdir}/pre-push"
