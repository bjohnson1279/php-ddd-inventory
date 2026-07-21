<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Models\WebhookSubscriptionModel;
use Ramsey\Uuid\Uuid;

class WebhookSubscriptionController
{
    public function create(RequestInterface $request, string $tenantId)
    {
        try {
            $validated = $request->validate([
                'targetUrl' => 'required|string',
                'secret' => 'required|string',
                'eventTypes' => 'required|array'
            ]);

            $id = Uuid::uuid4()->toString();
            $sub = WebhookSubscriptionModel::create([
                'id' => $id,
                'tenant_id' => $tenantId,
                'target_url' => $validated['targetUrl'],
                'secret' => $validated['secret'],
                'event_types' => json_encode($validated['eventTypes']),
                'is_active' => true

            return new Response([
                'id' => $sub->id,
                'tenantId' => $sub->tenant_id,
                'targetUrl' => $sub->target_url,
                'secret' => $sub->secret,
                'eventTypes' => json_decode($sub->event_types, true),
                'isActive' => (bool)$sub->is_active,
                'createdAt' => $sub->created_at
            ], 201);
        } catch (\Throwable $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function list(RequestInterface $request, string $tenantId)
    {
            $subs = WebhookSubscriptionModel::where('tenant_id', $tenantId)->get();
            $data = [];
            foreach ($subs as $sub) {
                $data[] = [
                    'id' => $sub->id,
                    'tenantId' => $sub->tenant_id,
                    'targetUrl' => $sub->target_url,
                    'secret' => $sub->secret,
                    'eventTypes' => json_decode($sub->event_types, true),
                    'isActive' => (bool)$sub->is_active,
                    'createdAt' => $sub->created_at
                ];
            }
            return new Response($data, 200);
            error_log('[WebhookSubscriptionController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    public function update(RequestInterface $request, string $tenantId, string $id)
    {
            $sub = WebhookSubscriptionModel::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$sub) {
                return new Response(['error' => 'Webhook subscription not found'], 404);
            }

            // Read the inputs manually since some fields might be optional
            $body = $request->validate([]); // trigger validator parsing
            
            if (isset($body['targetUrl'])) {
                $sub->target_url = $body['targetUrl'];
            }
            if (isset($body['secret'])) {
                $sub->secret = $body['secret'];
            }
            if (isset($body['eventTypes'])) {
                $sub->event_types = json_encode($body['eventTypes']);
            }
            if (isset($body['isActive'])) {
                $sub->is_active = (bool)$body['isActive'];
            }

            $sub->save();

            ], 200);
        }
    }

    public function delete(RequestInterface $request, string $tenantId, string $id)
    {

            }

            $sub->delete();
            return new Response(null, 204);
        }
    }
}



{
    {


        }
    }

    {
            }
            return new Response(['error' => $e->getMessage()], 500);
        }
    }

    {

            }

            
            }
            }
            }
            }


        }
    }

    {

            }

        }
    }
}
