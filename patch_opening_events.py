with open('src/Domain/Inventory/Services/OpeningBalanceService.php', 'r') as f:
    content = f.read()

import re

# We need to collect events and dispatch them after appendAll
content = re.sub(
    r'(        \$entries = \[\];\n        foreach \(\$onboarding->items\(\) as \$item\) \{\n            \$entry = new LedgerEntry\(\\Ramsey\\Uuid\\Uuid::uuid4\(\)->toString\(\), \$item->variantId, \$item->quantity, ReasonCode::OpeningBalance, \$actorId, \$onboarding->id, \$onboarding->asOfDate, \[\'unitCostCents\' => \$item->unitCostCents, \'locationId\' => \$onboarding->locationId\]\);\n            \$entries\[\] = \$entry;\n)            \$this->events->dispatch\(new \\InventoryApp\\Domain\\Inventory\\Events\\OpeningBalancePosted\(\n                \$onboarding->id,\n                \$item->variantId,\n                \$item->quantity,\n                \$item->unitCostCents,\n                \$onboarding->locationId,\n                \$onboarding->asOfDate,\n                new \\DateTimeImmutable\(\)\n            \)\);\n(        \}\n        \$this->ledger->appendAll\(\$entries\);)',
    r'\1            $eventsToDispatch[] = new \\InventoryApp\\Domain\\Inventory\\Events\\OpeningBalancePosted(\n                $onboarding->id,\n                $item->variantId,\n                $item->quantity,\n                $item->unitCostCents,\n                $onboarding->locationId,\n                $onboarding->asOfDate,\n                new \\DateTimeImmutable()\n            );\n\2\n        foreach ($eventsToDispatch as $event) {\n            $this->events->dispatch($event);\n        }',
    content
)

# Declare $eventsToDispatch = [];
content = re.sub(r'        \$entries = \[\];', r'        $entries = [];\n        $eventsToDispatch = [];', content)

with open('src/Domain/Inventory/Services/OpeningBalanceService.php', 'w') as f:
    f.write(content)
