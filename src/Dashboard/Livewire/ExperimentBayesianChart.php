<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Infrastructure\Database\Models\RollupModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Renders the Bayesian posterior distribution chart for each variant's
 * conversion rate. Uses a Beta(α, β) posterior with a uniform Beta(1,1) prior,
 * where α = conversions + 1 and β = (units − conversions) + 1.
 *
 * Only meaningful for binary/proportion metrics; shows an empty state for
 * experiments with no rollup data or zero conversions across all variants.
 */
final class ExperimentBayesianChart extends Component
{
    public string $experimentKey = '';

    private bool $shouldDispatchRefresh = false;

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        $this->shouldDispatchRefresh = true;
    }

    public function render(): View
    {
        ['series' => $series, 'labels' => $labels] = $this->buildPosteriors();

        if ($this->shouldDispatchRefresh) {
            $this->dispatch('bayesian-data-refreshed', hasData: ! empty($series));
            $this->shouldDispatchRefresh = false;
        }

        return view(
            'ab-testing::livewire.experiment-bayesian-chart',
            compact('series', 'labels'),
        );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Compute Beta posterior PDF series for each variant.
     *
     * @return array{
     *   series: list<array{key: string, color: string, points: list<float>, mean: float, credibleLow: float, credibleHigh: float}>,
     *   labels: list<float>
     * }
     */
    private function buildPosteriors(): array
    {
        $rollups = RollupModel::query()
            ->where('experiment_key', $this->experimentKey)
            ->get();

        if ($rollups->isEmpty()) {
            return ['series' => [], 'labels' => []];
        }

        $posteriorParams = [];

        foreach ($rollups as $rollup) {
            $conversions = (int) $rollup->conversions;
            $units       = (int) $rollup->count_of_units;

            // Skip variants with missing or incoherent data (conversions can never
            // exceed units for a binary metric; such rows indicate a rollup bug).
            if ($units < 2 || $conversions < 0 || $conversions > $units) {
                continue;
            }

            $alpha = $conversions + 1;      // uniform prior: Beta(1,1)
            $beta  = ($units - $conversions) + 1;

            $mean = $alpha / ($alpha + $beta);
            $var  = ($alpha * $beta) / (($alpha + $beta) ** 2 * ($alpha + $beta + 1));
            $sd   = sqrt($var);

            $posteriorParams[$rollup->variant_key] = compact('alpha', 'beta', 'mean', 'sd');
        }

        if ($posteriorParams === []) {
            return ['series' => [], 'labels' => []];
        }

        // Determine x range: ±4 standard deviations around the widest posterior,
        // clamped to (0.001, 0.999) to keep log(x) defined.
        $means  = array_column($posteriorParams, 'mean');
        $sds    = array_column($posteriorParams, 'sd');
        $maxSd  = max($sds);
        $xMin   = max(0.001, min($means) - 4.5 * $maxSd);
        $xMax   = min(0.999, max($means) + 4.5 * $maxSd);

        $numPoints = 200;
        $xValues   = [];

        for ($i = 0; $i < $numPoints; $i++) {
            $xValues[] = $xMin + ($xMax - $xMin) * $i / ($numPoints - 1);
        }

        $colors = ['#94a3b8', '#8b5cf6', '#06b6d4', '#f59e0b', '#10b981', '#f43f5e'];

        // Sort: control first, then alphabetically — same order as the time-series chart.
        $variantKeys = array_keys($posteriorParams);
        usort(
            $variantKeys,
            static fn ($a, $b) => ($a === 'control' ? -1 : ($b === 'control' ? 1 : strcmp($a, $b))),
        );

        $series = [];

        foreach ($variantKeys as $i => $variantKey) {
            $params = $posteriorParams[$variantKey];

            $points = array_map(
                fn (float $x): float => round(self::betaPdf($x, $params['alpha'], $params['beta']), 4),
                $xValues,
            );

            // 95% highest-density credible interval (for symmetric unimodal Beta, use percentile approx).
            [$credibleLow, $credibleHigh] = self::credibleInterval($params['alpha'], $params['beta'], 0.95);

            $series[] = [
                'key'         => $variantKey,
                'color'       => $colors[$i % count($colors)],
                'points'      => $points,
                'mean'        => round($params['mean'] * 100, 3),
                'credibleLow' => round($credibleLow * 100, 3),
                'credibleHigh' => round($credibleHigh * 100, 3),
            ];
        }

        // Labels are x values converted to percentages, rounded to 3 dp.
        $labels = array_map(static fn (float $x): float => round($x * 100, 3), $xValues);

        return compact('series', 'labels');
    }

    // ── Math helpers ──────────────────────────────────────────────────────────

    private static function betaPdf(float $x, float $alpha, float $beta): float
    {
        if ($x <= 0.0 || $x >= 1.0) {
            return 0.0;
        }

        $logPdf = ($alpha - 1.0) * log($x)
                + ($beta  - 1.0) * log(1.0 - $x)
                + self::lgamma($alpha + $beta)
                - self::lgamma($alpha)
                - self::lgamma($beta);

        return exp($logPdf);
    }

    /**
     * Approximate central 95% credible interval using the inverse-CDF (quantile)
     * of a Beta distribution via a Newton–Raphson refinement of an initial Normal
     * approximation. Accurate enough for display purposes.
     *
     * @return array{0: float, 1: float}
     */
    private static function credibleInterval(float $alpha, float $beta, float $credibility): array
    {
        $tail  = (1.0 - $credibility) / 2.0;
        $lower = self::betaQuantile($tail, $alpha, $beta);
        $upper = self::betaQuantile(1.0 - $tail, $alpha, $beta);

        return [$lower, $upper];
    }

    /**
     * Approximate Beta quantile using a Normal approximation as a seed, then
     * refined with 10 Newton–Raphson iterations.
     */
    private static function betaQuantile(float $p, float $alpha, float $beta): float
    {
        $mean = $alpha / ($alpha + $beta);
        $var  = ($alpha * $beta) / (($alpha + $beta) ** 2 * ($alpha + $beta + 1));
        $sd   = sqrt($var);

        // Normal approximation seed (Abramowitz & Stegun 26.2.17).
        $t = self::normalQuantile($p);
        $x = max(1e-6, min(1.0 - 1e-6, $mean + $sd * $t));

        // Newton–Raphson: x_{n+1} = x_n - (CDF(x_n) - p) / PDF(x_n)
        for ($iter = 0; $iter < 10; $iter++) {
            $cdf = self::betaIncomplete($x, $alpha, $beta);
            $pdf = self::betaPdf($x, $alpha, $beta);

            if (abs($pdf) < 1e-15) {
                break;
            }

            $x -= ($cdf - $p) / $pdf;
            $x  = max(1e-6, min(1.0 - 1e-6, $x));
        }

        return $x;
    }

    /**
     * Regularised incomplete Beta function I_x(α, β) via continued-fraction
     * expansion (Lentz's algorithm). Used by betaQuantile().
     */
    private static function betaIncomplete(float $x, float $alpha, float $beta): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }

        if ($x >= 1.0) {
            return 1.0;
        }

        // Use symmetry relation when x > (α+1)/(α+β+2) for better convergence.
        if ($x > ($alpha + 1.0) / ($alpha + $beta + 2.0)) {
            return 1.0 - self::betaIncomplete(1.0 - $x, $beta, $alpha);
        }

        $logBeta = self::lgamma($alpha) + self::lgamma($beta) - self::lgamma($alpha + $beta);
        $front   = exp(log($x) * $alpha + log(1.0 - $x) * $beta - $logBeta) / $alpha;

        // Continued fraction via Lentz's algorithm.
        $eps   = 1e-10;
        $tiny  = 1e-30;
        $f     = $tiny;
        $C     = $f;
        $D     = 0.0;

        for ($m = 0; $m <= 200; $m++) {
            for ($sub = 0; $sub <= 1; $sub++) {
                if ($m === 0 && $sub === 0) {
                    $numVal = 1.0;
                } elseif ($sub === 0) {
                    $numVal = -($alpha + $m) * ($alpha + $beta + $m) * $x
                              / (($alpha + 2 * $m - 1) * ($alpha + 2 * $m));
                } else {
                    $numVal = $m * ($beta - $m) * $x
                              / (($alpha + 2 * $m - 1) * ($alpha + 2 * $m));
                }

                $D = 1.0 + $numVal * $D;
                if (abs($D) < $tiny) { $D = $tiny; }
                $D = 1.0 / $D;

                $C = 1.0 + $numVal / $C;
                if (abs($C) < $tiny) { $C = $tiny; }

                $delta = $C * $D;
                $f    *= $delta;

                if (abs($delta - 1.0) < $eps) {
                    return $front * $f;
                }
            }
        }

        return $front * $f;
    }

    /**
     * Rational approximation of the standard Normal quantile (Beasley-Springer-Moro).
     */
    private static function normalQuantile(float $p): float
    {
        static $a = [2.50662823884, -18.61500062529, 41.39119773534, -25.44106049637];
        static $b = [-8.47351093090, 23.08336743743, -21.06224101826, 3.13082909833];
        static $c = [
            0.3374754822726147, 0.9761690190917186, 0.1607979714918209,
            0.0276438810333863, 0.0038405729373609, 0.0003951896511349,
            0.0000321767881768, 0.0000002888167364, 0.0000003960315187,
        ];

        $x = $p - 0.5;

        if (abs($x) < 0.42) {
            $r = $x * $x;
            $r = $x * ((($a[3] * $r + $a[2]) * $r + $a[1]) * $r + $a[0])
               / (((($b[3] * $r + $b[2]) * $r + $b[1]) * $r + $b[0]) * $r + 1.0);

            return $r;
        }

        $r = $p < 0.5 ? $p : 1.0 - $p;
        $r = log(-log($r));
        $r = $c[0] + $r * ($c[1] + $r * ($c[2] + $r * ($c[3] + $r * ($c[4]
           + $r * ($c[5] + $r * ($c[6] + $r * ($c[7] + $r * $c[8])))))));

        return $p < 0.5 ? -$r : $r;
    }

    /**
     * Lanczos approximation of the natural log-Gamma function. Accurate to
     * ~15 significant figures for Re(x) > 0.
     */
    private static function lgamma(float $x): float
    {
        static $c = [
            0.99999999999980993,
            676.5203681218851,
            -1259.1392167224028,
            771.32342877765313,
            -176.61502916214059,
            12.507343278686905,
            -0.13857109526572012,
            9.9843695780195716e-6,
            1.5056327351493116e-7,
        ];

        if ($x < 0.5) {
            return log(M_PI / abs(sin(M_PI * $x))) - self::lgamma(1.0 - $x);
        }

        $x  -= 1.0;
        $a   = $c[0];
        $t   = $x + 7.5;   // g = 7

        for ($i = 1; $i <= 8; $i++) {
            $a += $c[$i] / ($x + $i);
        }

        return 0.5 * log(2.0 * M_PI)
             + ($x + 0.5) * log($t)
             - $t
             + log($a);
    }
}
