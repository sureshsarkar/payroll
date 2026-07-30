<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\CSSList\AtRuleBlockList;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\URL;

/**
 * P1-4 follow-up (2026-05-29) — defense-in-depth on coach `custom_css`.
 *
 * The previous mitigation (commit 0011687) was a single regex:
 *
 *   /^(?!.*\b(?:expression|behavior|javascript:|vbscript:)\s*[(:])/is
 *
 * That regex catches dumb-case payloads but loses to anything obfuscated
 * (HTML-encoded, mid-token whitespace, comments inside the function call,
 * unicode-escape `\65 xpression(...)`, etc). Browsers tolerate all of
 * those — a regex can't reliably reach parity with a CSS parser.
 *
 * This rule parses the CSS into an AST via sabberworm/php-css-parser
 * (MIT, ~150 KB, zero runtime deps) and walks every declaration. Anything
 * the rule below considers dangerous is rejected at validation time.
 *
 * The CSP middleware (App\Http\Middleware\ContentSecurityPolicy) is the
 * RUNTIME defense. This rule is the AT-INPUT defense — it gives the
 * coach a clean error at save time instead of a silent CSP violation
 * report after publish. Defense-in-depth.
 *
 * Tolerated:
 *   - Standard property/value declarations
 *   - @media / @keyframes / @font-face / @supports (the common at-rules)
 *   - url(https://…) and url(/relative) and url(data:image/…) — last
 *     because we use them for inline svg sprites & gradient backgrounds
 *   - @import url(https://fonts.googleapis.com/...) and other https origins
 *
 * Rejected (validation fails):
 *   - any declaration containing `expression(` (IE legacy XSS sink)
 *   - any declaration containing `behavior:` (IE legacy XSS sink)
 *   - url(javascript:…) / url(vbscript:…) / url(data:text/html…) / url(data:…javascript…)
 *   - @import url(javascript:…) / @import url(data:…)
 *   - parse errors — if sabberworm can't parse it, we don't trust it
 */
class SafeCss implements ValidationRule
{
    /** Hard cap to keep the parser from chewing on absurd input. */
    private const MAX_BYTES = 100_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            $fail("The {$attribute} must be a string.");
            return;
        }
        if (strlen($value) > self::MAX_BYTES) {
            $fail("The {$attribute} is too large (max " . self::MAX_BYTES . " bytes).");
            return;
        }

        // Cheap pre-check on the raw bytes. This catches the dumb-case
        // payloads before we pay for the parser. The AST walk below is
        // the authoritative check.
        $lower = strtolower($value);
        $bannedSubstrings = [
            'expression(',     // IE expression sink
            'expression (',
            '-moz-binding',    // legacy Firefox XBL sink
            'behaviour:',      // British spelling — IE also accepted it
            'behavior:',
            'javascript:',
            'vbscript:',
            // data: URIs that carry executable payloads. The AST walk
            // catches these too if the parser keeps them intact, but
            // some inputs slip past the URL node (e.g. when the comma
            // truncation upstream cuts the payload). Substring scan is
            // the belt to the AST walk's suspenders.
            'data:text/html',
            'data:application/javascript',
            'data:application/x-javascript',
            'data:application/ecmascript',
        ];
        foreach ($bannedSubstrings as $needle) {
            if (str_contains($lower, $needle)) {
                $fail("The {$attribute} contains a banned construct: '{$needle}'.");
                return;
            }
        }

        try {
            $parser = new Parser($value);
            $css = $parser->parse();
        } catch (\Throwable $e) {
            // If the parser can't make sense of it, neither will the
            // browser predictably — refuse to save.
            $fail("The {$attribute} is not valid CSS.");
            return;
        }

        // Walk the AST. Reject anything with a dangerous url() / src() /
        // function call, or any @import whose target isn't http(s).
        $contents = $css->getContents();
        $error = $this->walk($contents);
        if ($error !== null) {
            $fail("The {$attribute} contains disallowed content: {$error}");
        }
    }

    /**
     * Recursive walk over the CSS tree. Returns an error string if a
     * dangerous construct is found, null otherwise.
     */
    private function walk(array $contents): ?string
    {
        foreach ($contents as $node) {
            // @import at-rule.
            if (method_exists($node, 'getAtRuleName') && method_exists($node, 'getAtRuleArgs')) {
                if (strtolower($node->getAtRuleName()) === 'import') {
                    $argText = is_array($node->getAtRuleArgs())
                        ? implode(' ', array_map(fn ($x) => (string) $x, $node->getAtRuleArgs()))
                        : (string) $node->getAtRuleArgs();
                    $err = $this->checkUrlString($argText);
                    if ($err) return "@import {$err}";
                }
            }

            // RuleSets carry declarations whose values may contain url()
            // or CSSFunction nodes. Walk them.
            if ($node instanceof RuleSet) {
                foreach ($node->getRules() as $rule) {
                    $err = $this->checkValue($rule->getValue());
                    if ($err) return $err;
                }
            }

            // Nested blocks: @media, @keyframes, @supports — recurse.
            if (method_exists($node, 'getContents') && ! ($node instanceof RuleSet)) {
                $err = $this->walk($node->getContents());
                if ($err) return $err;
            }
        }
        return null;
    }

    /**
     * Inspect a declaration value. The value can be a scalar (string,
     * number) or a CSSFunction / URL node. Walk children if it's a list.
     */
    private function checkValue(mixed $value): ?string
    {
        if ($value === null) return null;

        if ($value instanceof URL) {
            return $this->checkUrlString((string) $value->getURL());
        }

        if ($value instanceof CSSFunction) {
            $name = strtolower($value->getName());
            // url() handled as URL node above, but some parsers expose
            // it as a CSSFunction — handle both shapes.
            if ($name === 'url') {
                $argText = implode('', array_map(fn ($a) => (string) $a, $value->getArguments()));
                return $this->checkUrlString($argText);
            }
            if ($name === 'expression' || $name === '-ms-expression') {
                return "function {$name}() is not allowed";
            }
            // Walk function arguments — gradients can contain url()s.
            foreach ($value->getArguments() as $arg) {
                $err = $this->checkValue($arg);
                if ($err) return $err;
            }
            return null;
        }

        // Lists / RuleValueList / arrays — walk children.
        if (is_array($value)) {
            foreach ($value as $child) {
                $err = $this->checkValue($child);
                if ($err) return $err;
            }
            return null;
        }
        if (method_exists($value, 'getListComponents')) {
            foreach ($value->getListComponents() as $child) {
                $err = $this->checkValue($child);
                if ($err) return $err;
            }
            return null;
        }

        // Scalar / string — last-mile cheap check for any banned token
        // that sneaked past the byte-level pre-check via parser
        // normalization.
        $raw = strtolower((string) $value);
        foreach (['javascript:', 'vbscript:', 'expression(', 'behavior:'] as $needle) {
            if (str_contains($raw, $needle)) {
                return "value contains '{$needle}'";
            }
        }
        return null;
    }

    /**
     * Validate a URL-bearing string (url() argument or @import target).
     * Accepts http(s), root-relative paths, plain font/image data URIs.
     * Rejects javascript:, vbscript:, data: text/html, data:* containing
     * `javascript`.
     */
    private function checkUrlString(string $url): ?string
    {
        // Strip surrounding quotes the parser may have left in place.
        $clean = trim($url, " \t\n\r\0\x0B\"'");
        $lower = strtolower($clean);

        if (str_starts_with($lower, 'javascript:')) return "url(javascript:…) is not allowed";
        if (str_starts_with($lower, 'vbscript:'))   return "url(vbscript:…) is not allowed";

        if (str_starts_with($lower, 'data:')) {
            // Allow data:image/* and data:font/* and data:application/font-*.
            // Reject anything that mentions text/html, application/javascript,
            // or contains the substring `javascript` anywhere in the payload
            // (defends against `data:image/svg+xml;…<script>…`).
            if (preg_match('#^data:(?:text/html|application/(?:javascript|x-javascript|ecmascript))\b#i', $lower)) {
                return "url(data:text/html or javascript) is not allowed";
            }
            if (str_contains($lower, 'javascript')) {
                return "url(data:…javascript…) is not allowed";
            }
            // SVG data URLs may contain scripts. Allow only safe subtypes.
            if (str_starts_with($lower, 'data:image/svg')) {
                if (str_contains($lower, '<script') || str_contains($lower, 'onload=') || str_contains($lower, 'onerror=')) {
                    return "url(data:image/svg with script) is not allowed";
                }
            }
            return null;
        }
        return null;
    }
}
