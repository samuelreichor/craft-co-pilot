<?php

namespace samuelreichor\coPilot\enums;

enum SectionAccess: string
{
    case Blocked = 'blocked';
    case ReadOnly = 'readOnly';
    case ReadWrite = 'readWrite';
}
