<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use App\Models\Sale;

#[Signature('app:get-sales')]
#[Description('Command description')]
class GetSales extends Command
{
    public function handle()
    {
        $this->info("Начало выполнения команды...");
        $apiKey = 'E6kUTYrYwZq2tN4QEtyzsbEBk3ie';
        $dateFrom = '2000-01-01';
        $dateTo   = now()->format('Y-m-d');
        $baseUrl  = 'http://109.73.206.144:6969/api/sales';

        $firstResponse = Http::withoutVerifying()->get($baseUrl, [
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
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
                            'dateTo'   => $dateTo,
                            'page'     => $page,
                            'key'      => $apiKey,
                        ]);

                    $items = collect($response->json('data'))->map(function ($item) {
                        return [
                            'g_number'           => $item['g_number'],
                            'date'               => $item['date'],
                            'last_change_date'   => $item['last_change_date'],
                            'supplier_article'   => $item['supplier_article'],
                            'tech_size'          => $item['tech_size'],
                            'barcode'            => $item['barcode'],
                            'total_price'        => $item['total_price'],
                            'discount_percent'   => $item['discount_percent'],
                            'is_supply'          => $item['is_supply'],
                            'is_realization'     => $item['is_realization'],
                            'promo_code_discount'=> $item['promo_code_discount'],
                            'warehouse_name'     => $item['warehouse_name'],
                            'country_name'       => $item['country_name'],
                            'oblast_okrug_name'  => $item['oblast_okrug_name'],
                            'region_name'        => $item['region_name'],
                            'income_id'          => $item['income_id'],
                            'sale_id'            => $item['sale_id'],
                            'odid'               => $item['odid'],
                            'spp'                => $item['spp'],
                            'for_pay'            => $item['for_pay'],
                            'finished_price'     => $item['finished_price'],
                            'price_with_disc'    => $item['price_with_disc'],
                            'nm_id'              => $item['nm_id'],
                            'subject'            => $item['subject'],
                            'category'           => $item['category'],
                            'brand'              => $item['brand'],
                            'is_storno'          => $item['is_storno'],
                        ];
                    })->toArray();

                    Sale::upsert(
                        $items,
                        ['g_number', 'nm_id', 'date', 'barcode', 'subject'],
                        [
                            'last_change_date', 'supplier_article', 'tech_size',
                            'total_price', 'discount_percent', 'is_supply',
                            'is_realization', 'promo_code_discount', 'warehouse_name',
                            'country_name', 'oblast_okrug_name', 'region_name',
                            'income_id', 'sale_id', 'odid', 'spp', 'for_pay',
                            'finished_price', 'price_with_disc', 'category',
                            'brand', 'is_storno'
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

        $currentCount = Sale::count();
        $this->info("В эндпоинте всего: $total, в БД сейчас: $currentCount");
    }
}
