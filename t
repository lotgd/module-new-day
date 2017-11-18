#!/bin/bash -ex
phpunit tests/ModuleTest.php
phpunit tests/BeforeNewDayEventTest.php
phpunit tests/AfterNewDayEventTest.php
