<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Registered;
use App\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use App\Models\{Exam, Subject, Role};
use App\Observers\ExamObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register API routes manually
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
        // Model::preventLazyLoading(true);
        if (Schema::hasTable('subjects')) {
            Cache::remember('subjects', 3600, function () {
                return Subject::select('id','name','code','description')
                        ->where('status',1)
                        ->get();
            });
        }
        if (Schema::hasTable('roles')) {
            Cache::remember('roles', 3600, function () {
                return Role::select('id','name')->pluck('name','id');
            });
        }
        Exam::observe(ExamObserver::class);

        foreach ([
            \App\Models\Question::class, \App\Models\OptionQuestion::class, \App\Models\Notice::class,
            \App\Models\ExamGuide::class, \App\Models\ExamType::class, Subject::class, \App\Models\Blog\Blog::class,
        ] as $model) {
            $model::observe(\App\Observers\FrontendCacheObserver::class);
        }

        // Login / signup: per IP, route and account. OTP: per IP and route, since the code is the only input.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(implode('|', [
            $request->ip(), $request->path(), strtolower((string) ($request->input('email') ?? $request->input('username') ?? '')),
        ])));
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(10)->by($request->ip() . '|' . $request->path()));
    }

    public function register()
    {
        // Notices AI provider (config/notices.php -> ai.provider).
        $this->app->bind(\App\Services\Notices\Enrichment\NoticeAiClient::class, fn () => config('notices.ai.provider') === 'anthropic'
            ? new \App\Services\Notices\Enrichment\AnthropicNoticeAiClient()
            : new \App\Services\Notices\Enrichment\OpenRouterNoticeAiClient());

        // Marketing channels (config/marketing.php).
        $this->app->bind(\App\Services\Marketing\Channels\PushSender::class, \App\Services\Marketing\Channels\FcmPushSender::class);
        $this->app->bind(\App\Services\Marketing\Channels\SmsGateway::class, fn () => config('marketing.sms.driver') === 'sparrow'
            ? new \App\Services\Marketing\Channels\SparrowSmsGateway()
            : new \App\Services\Marketing\Channels\LogSmsGateway());
    }
}
