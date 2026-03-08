<?php

namespace samuelreichor\coPilot\enums;

enum ElementCreationBehavior: string
{
    case Draft = 'draft';
    case DirectSave = 'directSave';
    case Disabled = 'disabled';
}
