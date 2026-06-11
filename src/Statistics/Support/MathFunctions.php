<?php

declare(strict_types=1);

namespace ABTests\Statistics\Support;

use ValueError;

/**
 * Pure numerical helpers used by the analysis engines. No state, no I/O.
 * Implementations follow well-known polynomial approximations with documented
 * maximum errors so callers know the precision they're working with.
 */
final class MathFunctions
{
    private const float SQRT_TWO_PI = 2.5066282746310002;

    /**
     * Standard normal CDF, Φ(x).
     *
     * Uses the Abramowitz & Stegun rational approximation (26.2.17) with
     * maximum absolute error ≤ 7.5e-8.
     */
    public static function normalCdf(float $x): float
    {
        if ($x >= 8.0) {
            return 1.0;
        }

        if ($x <= -8.0) {
            return 0.0;
        }

        $t = 1.0 / (1.0 + 0.2316419 * abs($x));

        $poly = $t * (0.319381530
            + $t * (-0.356563782
                + $t * (1.781477937
                    + $t * (-1.821255978
                        + $t * 1.330274429))));

        $pdf = exp(-0.5 * $x * $x) / self::SQRT_TWO_PI;
        $tail = $pdf * $poly;

        return $x >= 0.0 ? 1.0 - $tail : $tail;
    }

    /**
     * Standard normal PDF, φ(x).
     */
    public static function normalPdf(float $x): float
    {
        return exp(-0.5 * $x * $x) / self::SQRT_TWO_PI;
    }

    /**
     * Inverse of the standard normal CDF (probit), Φ⁻¹(p).
     *
     * Uses Peter Acklam's rational approximation with maximum absolute error
     * ≤ 1.15e-9 for p ∈ (0, 1).
     *
     * @throws ValueError When p is outside (0, 1).
     */
    public static function normalQuantile(float $p): float
    {
        if ($p <= 0.0 || $p >= 1.0) {
            throw new ValueError('Probability p must be in (0, 1) exclusive.');
        }

        // Coefficients for the rational approximation.
        $a = [-3.969683028665376e+01, 2.209460984245205e+02,
              -2.759285104469687e+02, 1.383577518672690e+02,
              -3.066479806614716e+01, 2.506628277459239e+00];

        $b = [-5.447609879822406e+01, 1.615858368580409e+02,
              -1.556989798598866e+02, 6.680131188771972e+01,
              -1.328068155288572e+01];

        $c = [-7.784894002430293e-03, -3.223964580411365e-01,
              -2.400758277161838e+00, -2.549732539343734e+00,
               4.374664141464968e+00, 2.938163982698783e+00];

        $d = [7.784695709041462e-03, 3.224671290700398e-01,
              2.445134137142996e+00, 3.754408661907416e+00];

        $pLow = 0.02425;
        $pHigh = 1.0 - $pLow;

        if ($p < $pLow) {
            $q = sqrt(-2.0 * log($p));

            return (((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]
                / ((((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1.0));
        }

        if ($p <= $pHigh) {
            $q = $p - 0.5;
            $r = $q * $q;

            return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q
                / (((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1.0));
        }

        $q = sqrt(-2.0 * log(1.0 - $p));

        return -(((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]
            / ((((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1.0));
    }

    /**
     * Chi-square survival function (upper-tail p-value): P(X > x) where X ~ χ²(df).
     *
     * Uses the Wilson–Hilferty cube-root normal approximation, which is accurate
     * to 4–5 significant figures for df ≥ 1 and x > 0.
     *
     * @param int $df Degrees of freedom (≥ 1).
     */
    public static function chiSquareSurvivalFunction(float $x, int $df): float
    {
        if ($x <= 0.0) {
            return 1.0;
        }

        if ($df < 1) {
            return 1.0;
        }

        $k = (float) $df;
        $z = (($x / $k) ** (1.0 / 3.0) - (1.0 - 2.0 / (9.0 * $k))) / sqrt(2.0 / (9.0 * $k));

        return 1.0 - self::normalCdf($z);
    }
}
