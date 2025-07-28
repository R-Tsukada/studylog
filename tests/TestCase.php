<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // アクティブなトランザクションをクリア
        if (isset($this->app)) {
            $db = $this->app->make('db');
            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
        }
    }
    
    protected function refreshInMemoryDatabase()
    {
        // アクティブなトランザクションをクリア
        if (isset($this->app)) {
            $db = $this->app->make('db');
            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
        }
        
        // メモリ内データベースの場合、migrate:freshを使用
        if ($this->usingInMemoryDatabase()) {
            $this->artisan('migrate:fresh');
            return;
        }
        
        // 通常のRefreshDatabaseの処理
        parent::refreshInMemoryDatabase();
    }
    
    protected function usingInMemoryDatabase()
    {
        return config('database.connections.'.config('database.default').'.database') === ':memory:';
    }
}
