<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class DevProxyController extends Controller
{
    private const VITE = 'http://127.0.0.1:5173';
    public function __invoke(Request $request, ?string $path = null)
    {
        $uri = $path ? '/'.ltrim($path, '/') : '/';
        $query = $request->getQueryString();
        if ($query) {
            $uri .= '?'.$query;
        }
        $url = self::VITE.$uri;
        $method = strtoupper($request->method());
        $headers = [
            'Accept: '.($request->header('Accept') ?: '*/*'),
        ];
        if ($request->header('Accept-Language')) {
            $headers[] = 'Accept-Language: '.$request->header('Accept-Language');
        }
        if ($request->header('If-None-Match')) {
            $headers[] = 'If-None-Match: '.$request->header('If-None-Match');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_TCP_NODELAY => true,
        ]);
        if (! in_array($method, ['GET', 'HEAD'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getContent());
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            curl_close($ch);
            return response('Dev server unavailable. Chay: npm run dev trong laptop_fe', 502);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $skipHeaders = ['transfer-encoding', 'connection', 'content-encoding', 'content-length'];
        $outHeaders = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $lower = strtolower(trim($name));
            if (in_array($lower, $skipHeaders, true)) {
                continue;
            }
            $outHeaders[trim($name)] = trim($value);
        }
        return response($body, $status)->withHeaders($outHeaders);
    }
}

