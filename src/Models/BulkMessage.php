<?php

namespace Ihabrouk\Messenger\Models;

/**
 * BulkMessage Model (Deprecated)
 * 
 * @deprecated Use Batch model instead
 * This class exists for backward compatibility only
 */
class BulkMessage extends Batch
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'messenger_batches';
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Log deprecation warning
        if (config('app.debug')) {
            \Log::warning('BulkMessage model is deprecated. Use Batch model instead.', [
                'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? null
            ]);
        }
    }
}
