<?php

namespace samuelreichor\coPilot\enums;

enum AuditStatus: string
{
    case Success = 'success';
    case Denied = 'denied';
    case Error = 'error';
}
