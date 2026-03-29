<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use App\Models\Stock;

#[Signature('app:get-stocks')]
#[Description('Command description')]
class GetStocks extends Command
{
    public function handle()
    {
        $this->info("Начало выполнения команды...");

        $apiKey = 'E6kUTYrYwZq2tN4QEtyzsbEBk3ie';
        $dateFrom = now()->format('Y-m-d');
        $baseUrl  = 'http://109.73.206.144:6969/api/stocks';

        $firstResponse = Http::withoutVerifying()->get($baseUrl, [
            'dateFrom' => $dateFrom,
            'page'     => 1,
            'key'      => $apiKey,
        ]);

        $lastPage = $firstResponse->json('meta.last_page');
        $total = $firstResponse->json('meta.total');
        $this->info("Last page: $lastPage");

        for ($page = 1; $page <= $lastPage; $page++) {

            $this->info("Обработка страницы $page/$lastPage...");

            $attempt = 0;
            $success = false;

            while ($attempt < 3 && !$success) {

                try {
                    $response = Http::withoutVerifying()
                        ->timeout(120)
                        ->get($baseUrl, [
                            'dateFrom' => $dateFrom,
                            'page'     => $page,
                            'key'      => $apiKey,
                        ]);

                    $items = collect($response->json('data'))->map(function ($item) {
                        return [
                            'date'             => $item['date'],
                            'last_change_date' => $item['last_change_date'],
                            'supplier_article' => $item['supplier_article'],
                            'tech_size'        => $item['tech_size'],
                            'barcode'          => $item['barcode'],
                            'quantity'         => $item['quantity'],
                            'is_supply'        => $item['is_supply'],
                            'is_realization'   => $item['is_realization'],
                            'quantity_full'    => $item['quantity_full'],
                            'warehouse_name'   => $item['warehouse_name'],
                            'in_way_to_client' => $item['in_way_to_client'],
                            'in_way_from_client'=> $item['in_way_from_client'],
                            'nm_id'            => $item['nm_id'],
                            'subject'          => $item['subject'],
                            'category'         => $item['category'],
                            'brand'            => $item['brand'],
                            'sc_code'          => $item['sc_code'],
                            'price'            => $item['price'],
                            'discount'         => $item['discount'],
                        ];
                    })->toArray();

                    Stock::upsert(
                        $items,
                        ['date', 'barcode', 'nm_id', 'warehouse_name', 'quantity'],
                        [
                            'last_change_date', 'supplier_article', 'tech_size', 'is_supply',
                            'is_realization', 'quantity_full', 'in_way_to_client', 'in_way_from_client',
                            'subject', 'category', 'brand', 'sc_code', 'price', 'discount'
                        ]
                        
                    );

                    $success = true;

                } catch (RequestException $e) {
                    $this->error("Ошибка HTTP на странице $page (попытка $attempt):");
                    $this->error("Код: " . $e->response->status());
                    $this->error("Ответ: " . $e->response->body());

                } catch (ConnectionException $e) {
                    $this->error("Ошибка соединения на странице $page (попытка $attempt):");
                    $this->error($e->getMessage());

                } catch (\Exception $e) {
                    $this->error("Ошибка на странице $page (попытка $attempt):");
                    $this->error($e->getMessage());
                    $attempt++;
                    sleep(2);
                }
            }

            if (!$success) {
                $this->error("Страница $page пропущена после 3 попыток");
            }
        }
        
        $currentCount = Stock::count();
        $this->info("В эндпоинте всего: $total, в БД сейчас: $currentCount");
    }
}
