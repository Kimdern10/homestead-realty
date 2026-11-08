@php($rate = (float) \App\Models\Setting::get('mortgage_rate') ?: 6.5)

<x-layouts::site title="How much house can I afford?" description="Work out how much home you can afford from your income, debts and down payment. Free affordability calculator.">
    <div class="page-head">
        <div class="container">
            <h1>How much house can I afford?</h1>
            <p>Enter your income and monthly debts. We use the common lender guide: housing costs up to 28% of income, and all debts up to 36%.</p>
        </div>
    </div>

    <section class="section">
        <div class="container tool-layout">
            <form class="panel calc-form" data-afford onsubmit="return false">
                <div class="calc__fields">
                    <label class="field field--wide">Yearly household income (before tax)
                        <span class="calc__money"><span>$</span><input class="input" name="income" inputmode="numeric" value="95,000"></span>
                    </label>
                    <label class="field field--wide">Monthly debt payments <small class="muted">(car, student loans, cards)</small>
                        <span class="calc__money"><span>$</span><input class="input" name="debts" inputmode="numeric" value="450"></span>
                    </label>
                    <label class="field">Down payment
                        <span class="calc__money"><span>$</span><input class="input" name="down" inputmode="numeric" value="40,000"></span>
                    </label>
                    <label class="field">Interest rate
                        <span class="calc__pct"><input class="input" name="rate" inputmode="decimal" value="{{ $rate }}"><span>%</span></span>
                    </label>
                    <label class="field">Loan length
                        <select class="select" name="term">
                            <option value="30" selected>30 years</option>
                            <option value="20">20 years</option>
                            <option value="15">15 years</option>
                        </select>
                    </label>
                    <label class="field">Property tax rate
                        <span class="calc__pct"><input class="input" name="tax" inputmode="decimal" value="1.1"><span>%</span></span>
                    </label>
                    <label class="field">Home insurance (yearly)
                        <span class="calc__money"><span>$</span><input class="input" name="insurance" inputmode="numeric" value="1,800"></span>
                    </label>
                    <label class="field">HOA fees (monthly)
                        <span class="calc__money"><span>$</span><input class="input" name="hoa" inputmode="numeric" value="0"></span>
                    </label>
                </div>
            </form>

            <aside class="tool-aside" aria-live="polite">
                <p class="muted">You could afford a home up to about</p>
                <div class="big-number" data-out="price">$0</div>
                <ul class="legend" style="margin-top: 16px">
                    <li><span>Monthly payment</span><b data-out="monthly">$0</b></li>
                    <li><span>Loan amount</span><b data-out="loan">$0</b></li>
                    <li><span>Housing share of income</span><b data-out="share">0%</b></li>
                </ul>
                <p class="calc__note" data-out="note"></p>
                <a class="btn btn--primary btn--block" style="margin-top: 16px" data-afford-search href="{{ route('search', ['status' => 'for_sale']) }}">See homes in my budget</a>
                <p class="calc__note">A guide only, not a loan offer. A lender will look at your credit score and savings too.</p>
            </aside>
        </div>
    </section>
</x-layouts::site>
