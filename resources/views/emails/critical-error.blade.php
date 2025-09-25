<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Critical Error Alert</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .footer { background: #6c757d; color: white; padding: 15px; border-radius: 0 0 5px 5px; font-size: 12px; }
        .error-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #dc3545; }
        .context-item { margin: 5px 0; }
        .label { font-weight: bold; color: #495057; }
        .value { color: #6c757d; }
        pre { background: #f1f3f4; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Critical Error Alert</h1>
            <p><strong>{{ $app_name }}</strong> - {{ $timestamp }}</p>
        </div>
        
        <div class="content">
            <h2>Error Details</h2>
            <div class="error-details">
                <p><span class="label">Exception:</span> <span class="value">{{ get_class($exception) }}</span></p>
                <p><span class="label">Message:</span> <span class="value">{{ $exception->getMessage() }}</span></p>
                <p><span class="label">File:</span> <span class="value">{{ $exception->getFile() }}:{{ $exception->getLine() }}</span></p>
                <p><span class="label">Trace ID:</span> <span class="value">{{ $context['trace_id'] ?? 'N/A' }}</span></p>
            </div>

            <h3>Request Context</h3>
            <div class="context-item">
                <span class="label">URL:</span> <span class="value">{{ $context['url'] ?? 'N/A' }}</span>
            </div>
            <div class="context-item">
                <span class="label">Method:</span> <span class="value">{{ $context['method'] ?? 'N/A' }}</span>
            </div>
            <div class="context-item">
                <span class="label">IP Address:</span> <span class="value">{{ $context['ip'] ?? 'N/A' }}</span>
            </div>
            <div class="context-item">
                <span class="label">User ID:</span> <span class="value">{{ $context['user_id'] ?? 'Guest' }}</span>
            </div>
            <div class="context-item">
                <span class="label">Tenant ID:</span> <span class="value">{{ $context['tenant_id'] ?? 'N/A' }}</span>
            </div>

            @if(isset($context['user_agent']))
            <div class="context-item">
                <span class="label">User Agent:</span> <span class="value">{{ $context['user_agent'] }}</span>
            </div>
            @endif

            @if(isset($context['request_data']) && !empty($context['request_data']))
            <h3>Request Data</h3>
            <pre>{{ json_encode($context['request_data'], JSON_PRETTY_PRINT) }}</pre>
            @endif

            <h3>Stack Trace</h3>
            <pre>{{ $exception->getTraceAsString() }}</pre>
        </div>
        
        <div class="footer">
            <p>This is an automated alert from {{ $app_name }}.</p>
            <p>Application URL: <a href="{{ $app_url }}" style="color: #fff;">{{ $app_url }}</a></p>
            <p>Please investigate and resolve this issue as soon as possible.</p>
        </div>
    </div>
</body>
</html>
