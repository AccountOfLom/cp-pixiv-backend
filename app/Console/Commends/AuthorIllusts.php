<?php


namespace App\Console\Commends;


use App\Admin\Repositories\SystemConfig;
use App\Console\Common;
use App\Server\Pixiv;
use Illuminate\Console\Command;
use App\Models\Author as AuthorModel;
use Illuminate\Support\Facades\Log;

/**
 * 作者的作品列表
 * Class AuthorIllusts
 * @package App\Console\Commends
 */
class AuthorIllusts extends Command
{

    use Common;

    /**
     * 控制台命令 signature 的名称。
     *
     * @var string
     */
    protected $signature = 'author-illusts';

    /**
     * 控制台命令说明。
     *
     * @var string
     */
    protected $description = 'author-illusts';


    /**
     * 执行控制台命令
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     */
    public function handle()
    {
        //采集开关
        $switch = SystemConfig::getConfig(SystemConfig::AUTHOR_ILLUSTS_SWITCH);
        if (!$switch || $switch != SystemConfig::ENABLE) {
            echo '$switch' . $switch;
            return false;
        }

        //采集频率（分钟）
        $preTimeKey = "collection_author_illusts_time";
        $allow = $this->intervalAllow(SystemConfig::P_INTERVAL_AUTHOR_ILLUSTS, $preTimeKey);
        if (!$allow) {
            return false;
        }

        $author = (new AuthorModel())->where("is_collected_illust", 0)->orderBy("is_priority_collect", "desc")->first();
        if (!$author) {
            echo 2;
            return false;
        }

        //作品列表
        $pixiv = new Pixiv();
        $data = $pixiv->userIllusts($author->pixiv_id);

        if (!$data['illusts']) {
            Log::info("没有采集到此作者的作品 , pixiv_id:" . $author->pixiv_id);
            $author->is_collected_illust = 2;
            return false;
        }

        foreach ($data['illusts'] as $k => $v) {
            if (!$this->saveIllusts($v, 1)) {
                Log::error("作者的作品保存失败 , pixiv_id:" . $author->pixiv_id, '; data:' . json_encode($v));
                $author->is_collected_illust = 2;
                return false;
            }
        }

        $author->is_collected_illust = 1;
        $author->collected_illust_date = date('Ymd', time());
        $author->save();

        echo 'collection author illusts success';
    }
}