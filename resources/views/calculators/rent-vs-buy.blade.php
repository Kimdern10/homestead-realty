@php($rate = (float) \App\Models\Setting::get('mortgage_rate') ?: 6.5)

<x-layouts::site title="Rent or buy calculator" description="Is it cheaper to rent or buy? Compare the real cost of renting and buying over the years you plan to stay.">
    <div class="page-head">
        <div class="container">
            <h1>Should I rent or buy?</h1>
            <p>Buying has big costs up front and when you sell, but you build equity. Renting is flexible, and your savings can grow elsewhere. See which costs less for how long you plan to stay.</p>
        </div>
    </div>

    <section class="section">
        <div class="container tool-layout">
            <form class="panel calc-form" data-rentbuy onsubmit="return false">
                <div class="calc__fields">
                    <label class="field">Home price
                        <span class="calc__money"><span>$</span><input class="input" name="price" inputmode="numeric" value="425,000"></span>
                    </label>
                    <label class="field">Monthly rent for a similar home
                        <span class="calc__money"><span>$</span><input class="input" name="rent" inputmode="numeric" value="2,300"></span>
                    </label>
                    <label class="field">Down payment
                        <span class="calc__pct"><input class="input" name="down" inputmode="decimal" value="20"><span>%</span></span>
                    </label>
                    <label class="field">Interest rate
                        <span class="calc__pct"><input class="input" name="rate" inputmode="decimal" value="{{ $rate }}"><span>%</span></span>
                    </label>
                    <label class="field field--wide">How long will you stay?
                        <span class="range-row"><input type="range" name="years" min="1" max="15" value="7"><b data-out="years">7 years</b></span>
                    </label>
                    <details class="field--wide calc-more">
                        <summary>More assumptions</summary>
                        <div class="calc__fields" style="margin-top: 12px">
                            <label class="field">Home value growth / year
                                <span class="calc__pct"><input class="input" name="growth" inputmode="decimal" value="3"><span>%</span></span>
                            </label>
                            <label class="field">Rent increase / year
                                <span class="calc__pct"><input class="input" name="rent_growth" inputmode="decimal" value="3"><span>%</span></span>
                            </label>
                            <label class="field">Property tax rate
                                <span class="calc__pct"><input class="input" name="tax" inputmode="decimal" value="1.1"><span>%</span></span>
                            </label>
                            <label class="field">Upkeep / year (of price)
                                <span class="calc__pct"><input class="input" name="upkeep" inputmode="decimal" value="1"><span>%</span></span>
                            </label>
                            <label class="field">Buying costs
                                <span class="calc__pct"><input class="input" name="buy_costs" inputmode="decimal" value="3"><span>%</span></span>
                            </label>
                            <label class="field">Selling costs
                                <span class="calc__pct"><input class="input" name="sell_costs" inputmode="decimal" value="6"><span>%</span></span>
                            </label>
                            <label class="field">Return on savings / year
                                <span class="calc__pct"><input class="input" name="invest" inputmode="decimal" value="4"><span>%</span></span>
                            </label>
                            <label class="field">Home insurance (yearly)
                                <span class="calc__money"><span>$</span><input class="input" name="insurance" inputmode="numeric" value="1,800"></span>
                            </label>
                        </div>
                    </details>
                </div>
            </form>

            <aside class="tool-aside" aria-live="polite">
                <p class="muted" data-out="verdict-lead">Over 7 years</p>
                <div class="big-number" data-out="verdict">—</div>
                <ul class="legend" style="margin-top: 16px">
                    <li><span><i style="background: var(--brand)"></i> Real cost of buying</span><b data-out="buy">$0</b></li>
                    <li><span><i style="background: var(--rent)"></i> Real cost of renting</span><b data-out="rentcost">$0</b></li>
                    <li><span>Buying wins after</span><b data-out="breakeven">—</b></li>
                </ul>
                <div class="bars" data-bars aria-hidden="true"></div>
                <p class="calc__note">“Real cost” is what you pay minus what you get back: for buying, the home's sale money after the loan and selling costs; for renting, what your down payment would have earned saved instead.</p>
            </aside>
        </div>
    </section>
</x-layouts::site>
