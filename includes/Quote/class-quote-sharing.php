<?php
namespace OKSIA\Quote;

class QuoteSharing {

    private $repository;

    public function __construct() {
        $this->repository = new QuoteRepository();
    }

    public function generate_share_link($quote_id) {
        $quote = $this->repository->get($quote_id);

        if (!$quote) {
            return false;
        }

        $token = $quote->share_token;
        $share_url = home_url("/quote/{$token}/v{$quote->version}");

        $this->repository->update($quote_id, ['share_url' => $share_url]);

        return $share_url;
    }

    public function get_shared_quote($token) {
        $quote = $this->repository->get_by_token($token);

        if ($quote) {
            $this->repository->increment_view_count($quote->id);
        }

        return $quote;
    }

    public function revoke_share_link($quote_id) {
        $new_token = bin2hex(random_bytes(32));

        $this->repository->update($quote_id, ['share_token' => $new_token]);

        return $this->generate_share_link($quote_id);
    }

    public function is_shareable($quote) {
        return in_array($quote->status, ['draft', 'sent']);
    }

    public function get_share_stats($quote_id) {
        $quote = $this->repository->get($quote_id);

        return [
            'view_count' => $quote->view_count ?? 0,
            'share_url' => $quote->share_url ?? '',
            'last_shared' => $quote->updated_at ?? null
        ];
    }
}
