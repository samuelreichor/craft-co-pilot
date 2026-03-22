<?php

namespace samuelreichor\coPilot\enums;

enum AuditAction: string
{
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Search = 'search';
}
