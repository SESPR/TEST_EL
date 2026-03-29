<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use App\Models\Order;

#[Signature('app:get-orders')]
#[Description('Command description')]
class GetOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        $this->info("Начало выполнения команды...");

        $apiKey = 'E6kUTYrYwZq2tN4QEtyzsbEBk3ie';
        $dateFrom = '2000-01-01';
        $dateTo   = now()->format('Y-m-d');
        $baseUrl  = 'http://109.73.206.144:6969/api/orders';

        
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
                            'g_number'          => $item['g_number'],
                            'date'              => $item['date'],
                            'last_change_date' => $item['last_change_date'],
                            'supplier_article' => $item['supplier_article'],
                            'tech_size'         => $item['tech_size'],
                            'barcode'           => $item['barcode'],
                            'total_price'       => $item['total_price'],
                            'discount_percent' => $item['discount_percent'],
                            'warehouse_name'   => $item['warehouse_name'],
                            'oblast'            => $item['oblast'],
                            'income_id'        => $item['income_id'],
                            'odid'              => $item['odid'],
                            'nm_id'             => $item['nm_id'],
                            'subject'           => $item['subject'],
                            'category'          => $item['category'],
                            'brand'             => $item['brand'],
                            'is_cancel'         =>  $item['is_cancel'],
                            'cancel_dt'         => $item['cancel_dt'],
                        ];
                    })
                    ->toArray();

                    Order::upsert(
                        $items,
                        ['g_number', 'nm_id', 'date', 'barcode', 'subject'],
                        [
                            'last_change_date', 'supplier_article', 'tech_size', 'total_price',
                            'discount_percent', 'warehouse_name', 'oblast', 'income_id',
                            'odid', 'category', 'brand', 'is_cancel', 'cancel_dt'
                        ]
                    );
                    
                    $success = true;

                } catch (RequestException $e) {
                    // Ошибка HTTP
                    $status = $e->response->status();
                    $body = $e->response->body();

                    $this->error("Ошибка HTTP на странице $page (попытка $attempt):");
                    $this->error("Код: $status");
                    $this->error("Ответ: $body");

                } catch (ConnectionException $e) {
                    // Ошибка соединения
                    $this->error("Ошибка соединения на странице $page (попытка $attempt):");
                    $this->error($e->getMessage());

                } catch (\Exception $e) {
                    // Любая другая ошибка
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

        $currentCount = Order::count();
        $this->info("В эндпоинте всего: $total, в БД сейчас: $currentCount");
    }
}
