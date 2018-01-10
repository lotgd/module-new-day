#!/bin/bash -ex
vendor/bin/phpunit tests/ModuleTest.php
vendor/bin/phpunit tests/BeforeNewDayEventTest.php
vendor/bin/phpunit tests/AfterNewDayEventTest.php
