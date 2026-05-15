<?php

namespace Knob;

use Knob\Grammars\Grammar;
use Knob\Grammars\MySqlGrammar;
use Knob\Grammars\PostgresGrammar;
use Knob\Grammars\SqliteGrammar;
use Knob\Grammars\SqlServerGrammar;

enum Driver
{
    case MySQL;
    case PostgreSQL;
    case SQLite;
    case SQLServer;

    public function grammar(): Grammar
    {
        return match ($this) {
            self::MySQL => new MySqlGrammar(),
            self::PostgreSQL => new PostgresGrammar(),
            self::SQLite => new SqliteGrammar(),
            self::SQLServer => new SqlServerGrammar(),
        };
    }
}
