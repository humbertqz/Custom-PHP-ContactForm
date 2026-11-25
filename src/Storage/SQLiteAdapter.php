<?php

declare(strict_types=1);

namespace ContactModule\Storage;

final class SQLiteAdapter extends PdoStorage
{
    protected function duplicateClause(): string
    {
        return 'ON CONFLICT (uuid) DO NOTHING';
    }
}
