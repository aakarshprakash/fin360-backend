<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Proforma Invoice {{ $proforma->proforma_number }}</title>
<style>
    body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 36px; color: #333; font-size: 11px; }
    .header { text-align: center; margin-bottom: 6px; }
    .header .name { font-size: 17px; font-weight: bold; color: #1a1a1a; margin-bottom: 4px; }
    .header .line { font-size: 10.5px; color: #555; line-height: 1.6; }
    .state-line { text-align: center; font-size: 10px; color: #666; margin-bottom: 12px; }
    .title-bar { text-align: center; font-size: 14px; font-weight: bold; border: 1px solid #333; padding: 6px 0; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 1px; }
    .not-tax-note { text-align: center; font-size: 9px; color: #888; margin-bottom: 10px; font-style: italic; }
    table.meta { width: 100%; border-collapse: collapse; border: 1px solid #bbb; border-top: none; margin-bottom: 0; }
    table.meta td { vertical-align: top; padding: 8px 10px; font-size: 10.5px; }
    table.meta .to-col { width: 54%; border-right: 1px solid #bbb; }
    table.meta .label { color: #888; text-transform: uppercase; font-size: 8.5px; letter-spacing: 0.5px; }
    table.meta .cust-name { font-size: 12px; font-weight: bold; color: #1a1a1a; }
    table.meta .qmeta td { padding: 2px 0; font-size: 10.5px; }
    table.meta .qmeta .k { color: #888; width: 90px; }
    table.meta .qmeta .v { font-weight: bold; color: #1a1a1a; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 0; }
    table.items th, table.items td { border: 1px solid #bbb; padding: 6px 8px; text-align: right; font-size: 10px; }
    table.items th { background: #f4f4f4; text-transform: uppercase; font-size: 9px; text-align: center; }
    table.items td.desc { text-align: left; font-weight: bold; color: #222; }
    table.items td.hsn { text-align: center; font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; }
    table.items td.sno { text-align: center; color: #888; }
    table.totals-row { width: 100%; border-collapse: collapse; border: 1px solid #bbb; border-top: none; }
    table.totals-row .words-col { width: 55%; border-right: 1px solid #bbb; padding: 8px 10px; font-style: italic; font-size: 10.5px; color: #555; vertical-align: top; }
    table.totals-row .tot-col { vertical-align: top; padding: 0; }
    table.tot { width: 100%; border-collapse: collapse; }
    table.tot td { padding: 4px 10px; font-size: 10.5px; border-bottom: 1px solid #ddd; }
    table.tot td:first-child { color: #888; }
    table.tot td:last-child { text-align: right; font-weight: bold; }
    table.tot .net td { font-size: 12px; font-weight: bold; border-bottom: none; border-top: 2px solid #333; padding-top: 8px; }
    .terms-block { border: 1px solid #bbb; border-top: none; padding: 8px 10px; }
    .terms-title { font-size: 10.5px; font-weight: bold; margin-bottom: 6px; }
    table.terms { width: 100%; border-collapse: collapse; }
    table.terms td { padding: 2px 4px; font-size: 10px; vertical-align: top; }
    table.terms .k { color: #888; width: 150px; white-space: nowrap; }
    .extra-note { font-size: 10px; color: #555; margin-top: 6px; }
    table.footer-band { width: 100%; border-collapse: collapse; border: 1px solid #bbb; border-top: none; }
    table.footer-band td { padding: 8px 10px; vertical-align: bottom; font-size: 10px; }
    table.footer-band .sign-col { text-align: center; width: 220px; }
    .for-company { font-size: 10.5px; color: #555; margin-bottom: 36px; }
    .sign-line { border-top: 1px solid #999; padding-top: 6px; font-size: 10.5px; }
    .signature-img { display: block; max-height: 44px; max-width: 160px; margin: -30px auto 4px; }
    .company-logo-img { max-width: 64px; max-height: 64px; object-fit: contain; }
</style>
</head>
<body>
    @php
        $contactBits = array_filter([
            !empty($company['phone']) ? 'Phone No: ' . $company['phone'] : null,
            !empty($company['mobile']) ? 'Mobile No: ' . $company['mobile'] : null,
        ]);
        $webBits = array_filter([
            !empty($company['email']) ? 'Email: ' . $company['email'] : null,
            !empty($company['website']) ? 'Web: ' . $company['website'] : null,
        ]);
    @endphp
    <div class="header">
        @if(!empty($company['companyLogo']))
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:74px;vertical-align:middle;">
                    <img src="{{ storage_path('app/public/' . $company['companyLogo']) }}" class="company-logo-img" alt="Logo">
                </td>
                <td style="vertical-align:middle;text-align:center;">
                    <div class="name">{{ $company['companyName'] ?? 'Company Name' }}</div>
                    @if(!empty($company['address']))<div class="line">{{ $company['address'] }}</div>@endif
                    @if(count($contactBits))<div class="line">{{ implode('   ', $contactBits) }}</div>@endif
                    @if(count($webBits))<div class="line">{{ implode('   ', $webBits) }}</div>@endif
                </td>
                <td style="width:74px;"></td>
            </tr>
        </table>
        @else
        <div class="name">{{ $company['companyName'] ?? 'Company Name' }}</div>
        @if(!empty($company['address']))<div class="line">{{ $company['address'] }}</div>@endif
        @if(count($contactBits))<div class="line">{{ implode('   ', $contactBits) }}</div>@endif
        @if(count($webBits))<div class="line">{{ implode('   ', $webBits) }}</div>@endif
        @endif
    </div>
    @if(!empty($company['state']) || !empty($company['stateCode']))
    <div class="state-line">State: {{ $company['state'] ?? '—' }}{{ !empty($company['stateCode']) ? ', ' . $company['stateCode'] : '' }}</div>
    @endif

    <div class="title-bar">Proforma Invoice</div>
    <div class="not-tax-note">This is not a Tax Invoice — issued for advance payment / order confirmation purposes only.</div>

    <table class="meta">
        <tr>
            <td class="to-col">
                <div class="label">To</div>
                <div class="cust-name">{{ $proforma->customer_name ?: ($proforma->customer->name ?? '-') }}</div>
                @if(!empty($proforma->customer->address))<div>{{ $proforma->customer->address }}</div>@endif
                @if(!empty($proforma->customer->phone))<div>Phone No: {{ $proforma->customer->phone }}</div>@endif
                @if(!empty($proforma->customer->email))<div>Email: {{ $proforma->customer->email }}</div>@endif
                <div>GSTIN: {{ $proforma->customer->gst_number ?? '—' }}</div>
                @if(!empty($proforma->customer->state))<div>State: {{ $proforma->customer->state }}</div>@endif
            </td>
            <td>
                <table class="qmeta">
                    <tr><td class="k">Proforma No</td><td class="v">{{ $proforma->proforma_number }}</td></tr>
                    <tr><td class="k">Date</td><td class="v">{{ optional($proforma->date)->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">Valid Until</td><td class="v">{{ $proforma->valid_until ? $proforma->valid_until->format('d/m/Y') : '-' }}</td></tr>
                    @if($proforma->quotation)<tr><td class="k">Quote Ref</td><td class="v">{{ $proforma->quotation->quotation_number }}</td></tr>@endif
                    @if($proforma->currency && $proforma->currency !== 'INR')<tr><td class="k">Currency</td><td class="v">{{ $proforma->currency }}</td></tr>@endif
                    @if(!empty($company['gstNumber']))<tr><td class="k">Seller GSTIN</td><td class="v">{{ $company['gstNumber'] }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:30px">S.No</th>
                <th style="text-align:left">Description</th>
                <th style="width:70px">HSN/SAC</th>
                <th style="width:60px">Quantity</th>
                <th style="width:90px">Rate</th>
                <th style="width:80px">Discount</th>
                <th style="width:100px">Amount (Rs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proforma->items as $i => $item)
            <tr>
                <td class="sno">{{ $i + 1 }}</td>
                <td class="desc">{{ $item->description }}</td>
                <td class="hsn">{{ $item->hsn_code ?? '—' }}</td>
                <td>{{ number_format($item->quantity, 2) }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ $item->discount > 0 ? number_format($item->discount, 2) : '—' }}</td>
                <td>{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-row">
        <tr>
            <td class="words-col">
                <div class="label" style="margin-bottom:4px">Amount in Words</div>
                {{ \App\Helpers\NumberToWords::convert($proforma->total) }}
            </td>
            <td class="tot-col">
                <table class="tot">
                    <tr><td>Total</td><td>{{ number_format($proforma->subtotal, 2) }}</td></tr>
                    @if($proforma->discount > 0)
                    <tr><td>Special Discount</td><td>{{ number_format($proforma->discount, 2) }}</td></tr>
                    <tr><td>Sub Total</td><td>{{ number_format($proforma->subtotal - $proforma->discount, 2) }}</td></tr>
                    @endif
                    @if($proforma->tax > 0)
                    <tr><td>Tax (GST)</td><td>{{ number_format($proforma->tax, 2) }}</td></tr>
                    @endif
                    <tr class="net"><td>Net Amount</td><td>{{ number_format($proforma->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @php
        $termsRows = array_filter([
            'Validity' => $terms['validity'] ?? null,
            'GST' => $terms['gstNote'] ?? null,
            'Delivery Time' => $terms['deliveryTime'] ?? null,
            'Delivery Mode' => $terms['deliveryMode'] ?? null,
            'Warranty' => $terms['warranty'] ?? null,
            'Payment' => $terms['paymentTerms'] ?? null,
            'Computer Configuration' => $terms['computerConfiguration'] ?? null,
            'Training' => $terms['training'] ?? null,
            'Our Bank Details' => $company['bankDetails'] ?? null,
        ]);
    @endphp
    @if(count($termsRows) || $proforma->notes || $proforma->terms_and_conditions)
    <div class="terms-block">
        <div class="terms-title">Terms &amp; Conditions :</div>
        @if(count($termsRows))
        <table class="terms">
            @foreach($termsRows as $label => $value)
            <tr>
                <td class="k">{{ $label }}</td>
                <td>:</td>
                <td style="{{ $label === 'Our Bank Details' ? 'white-space:pre-line' : '' }}">{{ $value }}</td>
            </tr>
            @endforeach
        </table>
        @endif
        @if($proforma->notes)<div class="extra-note"><strong>Notes:</strong> {{ $proforma->notes }}</div>@endif
        @if($proforma->terms_and_conditions)<div class="extra-note"><strong>Additional Terms:</strong> {{ $proforma->terms_and_conditions }}</div>@endif
    </div>
    @endif

    <table class="footer-band">
        <tr>
            <td>
                <table class="terms">
                    @if(!empty($company['cin']))<tr><td class="k" style="width:80px">CIN</td><td>:</td><td>{{ $company['cin'] }}</td></tr>@endif
                    @if(!empty($company['gstNumber']))<tr><td class="k" style="width:80px">GSTIN</td><td>:</td><td>{{ $company['gstNumber'] }}</td></tr>@endif
                    @if(!empty($company['pan']))<tr><td class="k" style="width:80px">PAN No</td><td>:</td><td>{{ $company['pan'] }}</td></tr>@endif
                </table>
            </td>
            <td class="sign-col">
                <div class="for-company">For {{ $company['companyName'] ?? 'the Company' }}</div>
                @if(!empty($company['sealSignatureImage']) && $proforma->use_signature)
                    <img class="signature-img" src="{{ storage_path('app/public/' . $company['sealSignatureImage']) }}" alt="Seal & Signature">
                @endif
                <div class="sign-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
