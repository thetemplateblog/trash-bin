<?php

namespace TheTemplateBlog\TrashBin\Tests;

use TheTemplateBlog\TrashBin\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
