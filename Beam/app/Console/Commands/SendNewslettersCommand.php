<?php

namespace App\Console\Commands;

use App\Article;
use App\ArticlePageviews;
use App\ArticleTimespent;
use App\Contracts\Mailer\MailerContract;
use App\Conversion;
use App\Http\Controllers\NewsletterController;
use App\Model\NewsletterCriteria;
use App\Newsletter;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;
use Recurr\Exception;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;

class SendNewslettersCommand extends Command
{
    protected $signature = 'newsletters:send';

    protected $description = 'Process newsletters data and generate Mailer jobs.';

    private $transformer;

    private $mailer;

    public function __construct(MailerContract $mailer)
    {
        parent::__construct();

        $config = new ArrayTransformerConfig();
        // we need max 2 recurrences
        $config->setVirtualLimit(2);
        $this->transformer = new ArrayTransformer($config);
        $this->mailer = $mailer;
    }

    public function handle()
    {
        $this->line('');
        $this->line('<info>***** Sending newsletters *****</info>');
        $this->line('');

        $newsletters = Newsletter::where('state', Newsletter::STATE_STARTED)
            ->where('starts_at', '<=', Carbon::now())
            ->get();

        if ($newsletters->count() === 0) {
            $this->info("No newsletters to process");
            return;
        }

        foreach ($newsletters as $newsletter) {
            $nextSending = $newsletter->starts_at;
            $hasMore = false;

            if ($newsletter->rule_object) {
                [$nextSending, $hasMore] = $this->retrieveNextSending($newsletter);
            }

            if ($nextSending) {
                if ($nextSending->gt(Carbon::now())) {
                    // Not sending, date is in future
                    continue;
                }

                $this->line(sprintf("Processing newsletter: %s", $newsletter->name));
                $this->sendNewsletter($newsletter);
                $newsletter->last_sent_at = $nextSending;

                if (!$hasMore) {
                    $newsletter->state = Newsletter::STATE_FINISHED;
                }
            } else {
                $newsletter->state = Newsletter::STATE_FINISHED;
            }

            $newsletter->save();
        }
    }

    private function retrieveNextSending($newsletter)
    {
        // newsletter hasn't been sent yet, include all dates after starts_at (incl.)
        // if has been sent yet, count all dates after last_sent_at (excl.)
        $afterConstraint = $newsletter->last_sent_at ?
            new AfterConstraint($newsletter->last_sent_at, false) :
            new AfterConstraint($newsletter->starts_at, true);

        $recurrenceCollection = $this->transformer->transform($newsletter->rule_object, $afterConstraint);
        $nextSending = $recurrenceCollection->isEmpty() ? null : Carbon::instance($recurrenceCollection->first()->getStart());
        $hasMore = $recurrenceCollection->count() > 1;
        return [$nextSending, $hasMore];
    }

    private function sendNewsletter(Newsletter $newsletter)
    {
        $articles = $newsletter->personalized_content ? [] :
            NewsletterCriteria::getArticles($newsletter->criteria, $newsletter->timespan, $newsletter->articles_count);

        [$htmlContent, $textContent] = $this->generateEmail($newsletter, $articles);

        $templateId = $this->createTemplate($newsletter, $htmlContent, $textContent);

        $this->createJob($newsletter, $templateId);
    }

    private function createJob($newsletter, $templateId)
    {
        $jobId = $this->mailer->createJob($newsletter->segment_code, $newsletter->segment_provider, $templateId);
        $this->line(sprintf("Mailer job successfully created (id: %s)", $jobId));
    }

    private function createTemplate($newsletter, $htmlContent, $textContent): int
    {
        return $this->mailer->createTemplate(
            $newsletter->name,
            'beam_newsletter',
            'Newsletter generated by Beam',
            $newsletter->email_from,
            $newsletter->email_subject,
            $textContent,
            $htmlContent,
            $newsletter->mail_type_code
        );
    }

    private function generateEmail($newsletter, $articles)
    {
        $params = [];
        if ($newsletter->personalized_content) {
            $params['dynamic'] = true;
            $params['articles_count'] = $newsletter->articles_count;
        } else {
            $params['articles']=  implode("\n", $articles->pluck('url')->toArray());
        }

        $output = $this->mailer->generateEmail($newsletter->mailer_generator_id, $params);
        return [$output['htmlContent'], $output['textContent']];
    }
}
