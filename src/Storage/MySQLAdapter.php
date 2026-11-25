<?php

declare(strict_types=1);

namespace ContactModule\Storage;

final class MySQLAdapter extends PdoStorage
{
    protected function duplicateClause(): string
    {
        return 'ON DUPLICATE KEY UPDATE uuid = submissions.uuid';
    }
}
