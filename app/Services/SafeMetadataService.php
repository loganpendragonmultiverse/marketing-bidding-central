<?php

namespace App\Services;

use DOMDocument;
use RuntimeException;

class SafeMetadataService
{
    public const HANDLE_PLATFORMS = [
        'instagram' => ['label' => 'Instagram', 'url' => 'https://www.instagram.com/%s/'],
        'tiktok' => ['label' => 'TikTok', 'url' => 'https://www.tiktok.com/@%s'],
        'x' => ['label' => 'X / Twitter', 'url' => 'https://x.com/%s'],
        'youtube' => ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@%s'],
        'facebook' => ['label' => 'Facebook', 'url' => 'https://www.facebook.com/%s'],
        'github' => ['label' => 'GitHub', 'url' => 'https://github.com/%s'],
        'bluesky' => ['label' => 'Bluesky', 'url' => 'https://bsky.app/profile/%s'],
    ];

    public static function handlePlatformOptions(): array
    {
        return array_map(fn (array $platform): string => $platform['label'], self::HANDLE_PLATFORMS);
    }

    public function normalize(string $url, ?string $platform = null): array
    {
        $url = trim($url);
        $platform = strtolower(trim((string) $platform));

        if ($this->looksLikeHandle($url)) {
            if (! isset(self::HANDLE_PLATFORMS[$platform])) {
                throw new RuntimeException('Choose a social platform when entering a handle.');
            }

            $handle = ltrim($url, '@');
            if ($handle === '' || strlen($handle) > 100 || ! preg_match('/^[a-z0-9._-]+$/i', $handle)) {
                throw new RuntimeException('That social handle contains unsupported characters.');
            }
            $url = sprintf(self::HANDLE_PLATFORMS[$platform]['url'], $handle);
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) && ! preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('Enter a website, public social profile URL, or supported social handle.');
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException('Enter a website, public social profile URL, or supported social handle.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '' || strlen($host) > 253 || preg_match('/[^a-z0-9.:-]/i', $host)) {
            throw new RuntimeException('The website hostname is not supported.');
        }

        $scheme = strtolower($parts['scheme']);
        $port = isset($parts['port']) && ! (($scheme === 'https' && $parts['port'] === 443) || ($scheme === 'http' && $parts['port'] === 80)) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return ['url' => $scheme.'://'.$host.$port.$path.$query, 'host' => $host];
    }

    private function looksLikeHandle(string $value): bool
    {
        return str_starts_with($value, '@') || (! str_contains($value, '.') && ! str_contains($value, '/') && ! str_contains($value, ':'));
    }

    public function inspect(string $url): array
    {
        $current = $this->normalize($url)['url'];
        $maxRedirects = (int) config('marketplace.metadata.max_redirects');

        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            $normalized = $this->normalize($current);
            $ips = $this->publicIps($normalized['host']);
            [$status, $headers, $body] = $this->fetchPinned($normalized['url'], $normalized['host'], $ips[0]);

            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                if ($redirect === $maxRedirects) {
                    throw new RuntimeException('The website redirected too many times.');
                }
                $current = $this->resolveRedirect($normalized['url'], $headers['location']);

                continue;
            }

            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('The website did not return a successful response.');
            }

            return $this->parse($body, $normalized['url']);
        }

        throw new RuntimeException('Unable to inspect the website.');
    }

    public function assertPublicDestination(string $url): void
    {
        $normalized = $this->normalize($url);
        $this->publicIps($normalized['host']);
    }

    private function publicIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $ips = array_values(array_unique(array_filter(array_map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: []))));
        }

        if ($ips === []) {
            throw new RuntimeException("We couldn't find that website or profile. Check the spelling, or paste the full public profile URL.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Private or reserved network destinations are not accepted.');
            }
        }

        return $ips;
    }

    private function fetchPinned(string $url, string $host, string $ip): array
    {
        $body = '';
        $headers = [];
        $handle = curl_init($url);
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80));
        $resolvedIp = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int) config('marketplace.metadata.timeout_seconds'),
            CURLOPT_TIMEOUT => (int) config('marketplace.metadata.timeout_seconds'),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'MarketingBiddingCentral-Metadata/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolvedIp}"],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > (int) config('marketplace.metadata.max_bytes')) {
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($ok === false && $body === '') {
            throw new RuntimeException('Website inspection failed: '.$error);
        }

        return [$status, $headers, $body];
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/');

        return $origin.$directory.'/'.$location;
    }

    private function parse(string $html, string $url): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($document);

        $meta = [];
        foreach ($xpath->query('//meta[@content]') ?: [] as $node) {
            $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
            if ($key !== '') {
                $meta[$key] = trim($node->getAttribute('content'));
            }
        }

        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        $canonical = $xpath->query('//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]')->item(0)?->getAttribute('href');
        $icon = $xpath->query('//link[contains(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"icon")]')->item(0)?->getAttribute('href');

        return [
            'title' => mb_substr($title, 0, 180),
            'description' => mb_substr($meta['description'] ?? $meta['og:description'] ?? '', 0, 500),
            'image_url' => $this->absoluteUrl($url, $meta['og:image'] ?? null),
            'favicon_url' => $this->absoluteUrl($url, $icon ?: '/favicon.ico'),
            'canonical_url' => $this->absoluteUrl($url, $canonical),
            'inspected_url' => $url,
        ];
    }

    private function absoluteUrl(string $base, ?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, '//')) {
            return parse_url($base, PHP_URL_SCHEME).':'.$value;
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $origin.'/'.ltrim($value, '/');
    }
}
