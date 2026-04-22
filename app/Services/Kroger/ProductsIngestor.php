<?php

class ProductsIngestor
{
    public function __construct(
        private KrogerHttpClient $client,
        private ProductRepository $products
    ) {}

    public function syncByTerm(string $term, string $locationId): void
    {
        $page = 1;
        $pageSize = 50;

        do {
            $response = $this->client->get('/v1/products', [
                'filter.term'      => $term,
                'filter.locationId'=> $locationId,
                'filter.limit'     => $pageSize,
                'filter.start'     => ($page - 1) * $pageSize,
            ]);

            $data = $response['data'] ?? [];
            foreach ($data as $product) {
                $this->products->upsertFromKrogerProduct($product, $locationId);
            }

            $page++;
        } while (!empty($data));
    }
}
