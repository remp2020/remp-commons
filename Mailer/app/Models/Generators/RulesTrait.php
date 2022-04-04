<?php

namespace Remp\Mailer\Models\Generators;

trait RulesTrait
{
    private $linksColor = "#1F3F83";

    public function getRules($generatorRules = [])
    {
        [
            $captionTemplate,
            $captionWithLinkTemplate,
            $liTemplate,
            $hrTemplate,
            $spacerTemplate,
            $imageTemplate
        ] = $this->getTemplates();

        $rules = [
            // remove shortcodes
            "/\[pullboth.*?\/pullboth\]/is" => "",
            "/<script.*?\/script>/is" => "",
            "/\[iframe.*?\]/is" => "",
            '/\[\/?lock\]/i' => "",
            '/\[lock newsletter\]/i' => "",
            '/\[lock\]/i' => "",
            '/\[lock e\]/i' => "",

            // remove new style of shortcodes
            '/<div.*?class=".*?">/is' => '',
            '/<\/div>/is' => '',

            // remove iframes
            "/<iframe.*?\/iframe>/is" => "",

            // remove paragraphs
            '/<p.*?>(.*?)<\/p>/is' => "$1",

            // replace em-s
            "/<em.*?>(.*?)<\/em>/is" => "<i style=\"margin:0 0 26px 0;color:#181818;padding:0;font-size:18px;line-height:160%;text-align:left;font-weight:normal;word-wrap:break-word;-webkit-hyphens:auto;-moz-hyphens:auto;hyphens:auto;border-collapse:collapse !important;\">$1</i><br>",

            // remove new lines from inside caption shortcode
            "/\[caption.*?\/caption\]/is" => function ($matches) {
                return str_replace(array("\n\r", "\n", "\r"), '', $matches[0]);
            },

            // replace captions
            '/\[caption.*?\].*?href="(.*?)".*?src="(.*?)".*?\/a>(.*?)\[\/caption\]/im' => $captionWithLinkTemplate,
            '/\[caption.*?\].*?src="(.*?)".*?\/>(.*?)\[\/caption\]/im' => $captionTemplate,

            // replace link shortcodes
            '/\[articlelink.*?id="?(\d+)"?.*?\]/is' => function ($matches) {
                $url = "https://dennikn.sk/{$matches[1]}";
                $meta = $this->content->fetchUrlMeta($url);
                return '<a href="' . $url . '" style="padding:0;margin:0;line-height:1.3;color:' . $this->linksColor . ';text-decoration:underline;">' . $meta->getTitle() . '</a>';
            },

            // replace hrefs
            '/<a.*?href="(.*?)".*?>(.*?)<\/a>/is' => '<a href="$1" style="padding:0;margin:0;line-height:1.3;color:' . $this->linksColor . ';text-decoration:underline;">$2</a>',

            // replace h2
            '/<h2.*?>(.*?)<\/h2>/is' => '<h2 style="color:#181818;padding:0;line-height:1.3;font-weight:bold;text-align:left;margin:0 0 30px 0;font-size:24px;">$1</h2>' . PHP_EOL,

            // replace images
            '/<img.*?src="(.*?)".*?>/is' => $imageTemplate,

            // replace ul & ol
            '/<ul.*?>/is' => '<table style="border-spacing:0;border-collapse:collapse;vertical-align:top;color:#181818;padding:0;margin:0;line-height:1.3;text-align:left;font-family:\'Helvetica Neue\', Helvetica, Arial;width:100%;"><tbody>',
            '/<ol.*?>/is' => '<table style="border-spacing:0;border-collapse:collapse;vertical-align:top;color:#181818;padding:0;margin:0;line-height:1.3;text-align:left;font-family:\'Helvetica Neue\', Helvetica, Arial;width:100%; font-weight: normal;"><tbody>',

            '/<\/ul>/is' => '</tbody></table>' . PHP_EOL,
            '/<\/ol>/is' => '</tbody></table>' . PHP_EOL,

            // replace li
            '/<li.*?>(.*?)<\/li>/is' => $liTemplate,

            // hr
            '/(<hr>|<hr \/>)/is' => $hrTemplate,

            // parse embeds
            '/^\s*(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?\s*$/im' => function ($matches) {
                return $this->embedParser->parse($matches[0]);
            },

            // remove br from inside of a
            '/<a.*?\/a>/is' => function ($matches) {
                return str_replace('<br />', '', $matches[0]);
            },

            // greybox
            "/\[greybox\](.*?)\[\/greybox\]/is" => '<div class="t_greybox" style="padding: 16px; background: #f6f6f6;">$1</div>',
            '/\[row\](.*?)\[\/row\]/is' => '<div class="t_row gutter_8" style="display: flex; flex-wrap: wrap; margin: 0 -8px">$1</div>',
            '/\[col\](.*?)\[\/col\]/is' => '<div class="t_col large_0 small_0" style="margin: 0 8px; flex: 1">$1</div>',
        ];

        // keeps first occurrence of rule key, generator rules have priority over general rules
        return $generatorRules + $rules;
    }
}