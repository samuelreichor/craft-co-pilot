<?php

namespace samuelreichor\coPilot\enums;

enum ToolAction: string
{
    case Read = 'read';
    case Write = 'write';
    case Search = 'search';
}
