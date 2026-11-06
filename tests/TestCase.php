<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Update Database & Go-Live Reset memanggil set_time_limit(300) untuk
        // request production. Di test, batas itu ikut berlaku untuk SELURUH sisa
        // proses phpunit → regresi penuh mati "Maximum execution time" di tengah.
        // Reset per test supaya hanya test itu sendiri yang dibatasi waktu.
        set_time_limit(0);
    }
}
