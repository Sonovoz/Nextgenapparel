# Terms of sale

@include('legal.en._titular')

## 1. Seller

{!! $f($e['razon_social'], 'razón social') !!}, tax ID {!! $f($e['nif'], 'NIF') !!}, registered at {!! $f($e['direccion'], 'domicilio') !!}, {!! $f($e['codigo_postal'], 'código postal') !!} {!! $f($e['localidad'], 'localidad') !!} (Spain). Contact: {!! $f($e['correo'], 'correo de contacto') !!}.

By confirming an order you accept these terms, which are stored with it.

## 2. Products and prices

- Every garment **is printed with your design when you order**. Before confirming you see the mock-up, the sizes and the quality of each design.
- Prices are in euros. Each order has a minimum number of units per garment, shown when you configure it. Offers and coupons are applied in the cart under their own conditions.
- Shipping and taxes (VAT and, for Spanish retailers, the equivalence surcharge) are shown before you confirm.

## 3. How to buy

1. Configure the garments, colours, sizes and designs and add them to the cart.
2. Enter your details and delivery address: you will see shipping and the total.
3. Accept these terms and confirm the order. If you buy without an account, confirm your email with the link we send you.
4. Pay for the order. If it is not paid within {{ $diasSinPago }} days, it is cancelled automatically.

## 4. Payment

Payment is made on the secure page of **Redsys**, the Spanish banks' payment platform, by card or Bizum. Card details never reach our server. The order is marked as paid when Redsys confirms the charge, and you then receive the invoice.

## 5. Design review and production

After payment we review the designs. If one cannot be printed well, we write to you so you can change it. If a design infringes third-party rights or a trademark, we may reject the order and refund you.

**Your designs and their rights.** By providing images, logos or text to be printed, you declare that you own them or are authorised to use and print them, and that they do not infringe third-party rights (copyright, trademark, image rights or any other). You make this declaration when you accept these terms on confirming the order. You are solely responsible for it: if a third party makes a claim over a design you have provided, you answer for that claim and hold {!! $f($e['razon_social'], 'razón social') !!} harmless from any resulting damages, costs and penalties.

## 6. Cancelling the order

You can cancel the order from its page **until it goes into printing**. If you have already paid, we refund the full amount to the same payment method and issue the credit note.

**Once the garments are printed, the order can no longer be cancelled or changed**: each garment is made exclusively with your design and cannot be sold to anyone else.

## 7. No right of withdrawal

The garments are made **to your specifications and personalised**, so there is no right of withdrawal (article 103.c of the Spanish consumer protection law, Real Decreto Legislativo 1/2007). Therefore returns because you changed your mind, size or colour are not accepted once the order has been made. Before confirming, check the mock-up, the sizes (each garment has its size guide), the colours and the quantities carefully.

## 8. Guarantee, issues and returns

The garments carry the **3-year legal guarantee** for lack of conformity. If something arrives wrong (a printing defect, a damaged garment, a size or colour you did not order, or a missing garment), report an **issue** from your order page within {{ $diasIncidencia }} days of delivery, with photos if you can. After that period you can write to {!! $f($e['correo'], 'correo de contacto') !!} within the legal guarantee.

**How we resolve it.** If the mistake is ours, we reprint the affected garments or refund them, at no cost to you: if you need to send them back, we pay the shipping. Refunds go to the same payment method within 14 days of our accepting them.

**What is not a defect:**

- Small colour differences between the screen and the garment, or between the manufacturer's batches.
- Small variations in the position and size of the design, within the usual tolerances of textile printing.
- The result of a low-quality design that you accepted after seeing the quality warning.
- Mistakes in the design itself (texts, spelling, colours) or in the sizes and quantities you chose.
- Wear from use or from not following the washing and care instructions.

## 9. Customer service and law

You can write to us at {!! $f($e['correo'], 'correo de contacto') !!}. These terms are governed by Spanish law; consumers may go to the courts of their place of residence.
