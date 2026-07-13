<?php /** @var array<int,\App\Models\Ticket> $tickets */ ?>
<div class="table-wrap" style="border:0;border-radius:0">
    <table class="data">
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr onclick="location='/desk/tickets/<?= $t->id() ?>'" style="cursor:pointer">
                <td style="width:100px"><a class="ref" href="/desk/tickets/<?= $t->id() ?>"><?= e($t->reference()) ?></a></td>
                <td><?= e($t->subject()) ?><div class="muted" style="font-size:12px"><?= e($t->requesterName()) ?><?= $t->companyName() ? ' · ' . e($t->companyName()) : '' ?></div></td>
                <td style="width:120px">
                    <span class="pill" style="background:color-mix(in srgb,<?= e($t->priorityColour()) ?> 15%,transparent);color:<?= e($t->priorityColour()) ?>">
                        <span class="d"></span><?= e($t->priorityName()) ?>
                    </span>
                </td>
                <td style="width:150px">
                    <span class="pill" style="background:color-mix(in srgb,<?= e($t->statusColour()) ?> 15%,transparent);color:<?= e($t->statusColour()) ?>">
                        <span class="d"></span><?= e($t->statusName()) ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
