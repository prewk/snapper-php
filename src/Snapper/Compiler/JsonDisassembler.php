<?php
/**
 * JsonDisassembler
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;
use Illuminate\Support\Arr;

/**
 * JsonDisassembler
 */
class JsonDisassembler
{
    /**
     * Disassemble a string into 
     * 
     * @param array $full
     * @param array $tokens
     * @return array
     */
    public function disassemble(array $full, array $tokens): array
    {
        $parts = [json_encode($full)];

        // Iterate through tokens
        foreach ($tokens as $token) {
            $wip = [];

            // Iterate through current parts
            foreach ($parts as $part) {
                // If this part is a token, save it and continue
                if (in_array($part, $tokens)) {
                    $wip[] = $part;
                    continue;
                }

                // Explode this part with token as delimiter
                $exploded = explode((string)$token, $part);
                $results = [];

                // Interleave token into split points
                foreach ($exploded as $index => $subPart) {
                    if ($index > 0) {
                        $results[] = $token;
                    }

                    $results[] = $subPart;
                }

                // Gather our interleaved array
                $wip[] = $results;
            }

            // Flatten into one large array
            $parts = Arr::flatten($wip);
        }

        // Remove quotation marks if tokens are strings
        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if (
                in_array($part, $tokens) && // If part is token
                is_string($part) && // Token is a string
                $i > 0 && // There exists a part before
                $i < count($parts) - 1 && // There exists a part after
                substr($parts[$i - 1], -1) === "\"" && // The part before ends with a "
                $parts[$i + 1][0] === "\"" // The part after starts with a "
            ) {
                // Remove quotation marks
                $parts[$i - 1] = substr($parts[$i - 1], 0, -1);
                $parts[$i + 1] = substr($parts[$i + 1], 1);
            }
        }

        return $parts;
    }
}