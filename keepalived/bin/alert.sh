#!/bin/bash

TOKEN="1677765890:UPocIwrAoA8h4bpy4U5rZ6eOsFcY3V3rpZH4kbh9"
CHAT_ID="@ava_ha_notifs"
MESSAGE="$1"

curl -s -X POST https://tapi.bale.ai/bot$TOKEN/sendMessage -d chat_id=$CHAT_ID -d text="$MESSAGE" > /dev/null
