<?php namespace TingFeng\BaiduSiteMapPush;

use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use System\Classes\PluginBase;
use System\Classes\PluginManager;
use System\Classes\SettingsManager;
use Tingfeng\Baidusitemappush\Models\Settings;

/**
 * BaiduSiteMapPush Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'BaiduSiteMapPush',
            'description' => 'No description provided yet...',
            'author'      => 'TingFeng',
            'icon'        => 'icon-leaf'
        ];
    }

    public function boot()
    {
        $settings = Settings::instance();
        if (PluginManager::instance()->hasPlugin('RainLab.Blog')) {
            if (!is_null($settings->baidu_sitemap_push_url) && !empty($settings->baidu_sitemap_push_url)) {
                if ($settings->enable_baidu_sitemap_push_for_blog_post) {
                    $this->blogPostPush($settings->baidu_sitemap_push_url);
                }
            }
        }

        if (PluginManager::instance()->hasPlugin('Indikator.News')) {
            if (!is_null($settings->baidu_sitemap_push_url) && !empty($settings->baidu_sitemap_push_url)) {
                if ($settings->enable_baidu_sitemap_push_for_news_post) {
                    $this->newsPostPush($settings->baidu_sitemap_push_url);
                }
            }
        }
    }

    /**
     * 博客文章推送
     * @param string $requestUrl
     */
    protected function blogPostPush(string $requestUrl)
    {
        if (class_exists("\RainLab\Blog\Models\Post")) {
            \RainLab\Blog\Models\Post::extend(function ($model) use ($requestUrl) {
                $model->bindEvent("model.saveInternal", function ($attributes, $options) use ($model, $requestUrl) {
                    $theme = Theme::getActiveTheme();
                    $pages = CmsPage::listInTheme($theme, true);

                    $pushUrls = [];
                    foreach ($pages as $page) {
                        if (!$page->hasComponent('blogPost')) {
                            continue;
                        }
                        $properties = $page->getComponentProperties('blogPost');

                        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['slug'], $matches)) {
                            return;
                        }
                        $paramName = substr(trim($matches[1]), 1);

                        if (isset($attributes[$model->getKeyName()])) {
                            $category = $model->find($attributes[$model->getKeyName()]);

                            $params = [
                                $paramName => $category->slug,
                                'year'  => $category->published_at->format('Y'),
                                'month' => $category->published_at->format('m'),
                                'day'   => $category->published_at->format('d')
                            ];

                            $pushUrls[] = CmsPage::url($page->getBaseFileName(), $params);
                        }else {
                            $params = [
                                $paramName => $attributes['Post']['slug'],
                            ];
                            if (!empty($attributes['Post']['published_at'])) {
                                $publishedAt = Carbon::parse($attributes['Post']['published_at']);
                                $params['year'] = $publishedAt->format("Y");
                                $params['month'] = $publishedAt->format("m");
                                $params['day'] = $publishedAt->format("d");
                            }

                            $pushUrls[] = CmsPage::url($page->getBaseFileName(), $params);
                        }
                    }

                    if (count($pushUrls) > 0) {
                        self::push($requestUrl, $pushUrls);
                    }

                    return null;
                });
            });
        }
    }

    /**
     * 新闻文章推送
     * @param string $requestUrl
     */
    protected function newsPostPush(string $requestUrl)
    {
        if (class_exists("Indikator\News\Models\Posts")) {
            \Indikator\News\Models\Posts::extend(function ($model) use ($requestUrl) {
                $model->bindEvent("model.saveInternal", function ($attributes, $options) use ($model, $requestUrl) {
                    $theme = Theme::getActiveTheme();
                    $pages = CmsPage::listInTheme($theme, true);

                    if ($attributes['status'] != '1') {
                        return null;
                    }
                    $pushUrls = [];
                    foreach ($pages as $page) {
                        if (!$page->hasComponent('newsPost')) {
                            continue;
                        }
                        $properties = $page->getComponentProperties('newsPost');

                        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['slug'], $matches)) {
                            return;
                        }

                        $category = $model->find($attributes[$model->getKeyName()]);

                        $paramName = substr(trim($matches[1]), 1);

                        $params = [
                            $paramName => $category->slug
                        ];

                        $pushUrls[] = CmsPage::url($page->getBaseFileName(), $params);
                    }

                    if (count($pushUrls) > 0) {
                        self::push($requestUrl, $pushUrls);
                    }

                    return null;
                });
            });
        }
    }

    /**
     * 推送
     * @param string $requestUrl
     * @param array $pushUrls
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected static function push(string $requestUrl, array $pushUrls)
    {
        $client = new Client();

        try {
            $response = $client->post($requestUrl, [
                'body' => implode("\n", $pushUrls),
            ]);
            $content = $response->getBody()->getContents();
            $json = json_decode($content);
            if (property_exists($json, "not_same_site")) {
                Log::error("Non local URL", $json->not_same_site);
            }
            if (property_exists($json, "not_valid")) {
                Log::error("Illegal URL list", $json->not_valid);
            }
        }catch (\Exception $exception) {
            Log::error("Error pushing".$exception->getMessage());
        }
    }

    public function registerSettings()
    {
        return [
            'baidusitemappush' => [
                'label'       => 'tingfeng.baidusitemappush::lang.name',
                'description' => 'tingfeng.baidusitemappush::lang.name',
                'category'    => SettingsManager::CATEGORY_MYSETTINGS,
                'icon'        => 'icon-globe',
                'class'         => 'Tingfeng\Baidusitemappush\Models\Settings',
                'order'       => 500,
                'keywords'    => 'Baidu Sitemap Push'
            ]
        ];
    }
}
