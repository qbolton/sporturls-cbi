#!/bin/bash
echo http://www.ncaa.com/scoreboard/baseball/d1/$(date --date="$1" +%Y/%m/%d)
