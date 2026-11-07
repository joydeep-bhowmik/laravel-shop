<?php

use Illuminate\Support\Facades\Gate;

use function Laravel\Folio\{name, render};

name('admin.home');

render(fn () => redirect()->route(Gate::allows('reports.view') ? 'admin.dashboard' : 'admin.orders.index'));
?>
