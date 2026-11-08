<?php

return [
    'read_per_minute' => (int) env('WORKSPACE_READ_PER_MINUTE', 600),
    'write_per_minute' => (int) env('WORKSPACE_WRITE_PER_MINUTE', 60),
];
