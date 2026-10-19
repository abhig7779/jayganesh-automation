import type { Metadata } from "next";
import LegalShell, { ContactBox } from "../components/LegalShell";
import { site } from "@/lib/site";

export const metadata: Metadata = {
  title: "Terms & conditions",
  description: `Terms for products, fitting and services at ${site.name}.`,
};

export default function Terms() {
  return (
    <LegalShell title="Terms & conditions" current="terms">
      <p>
        These terms apply when you buy products from {site.name}, have work done on your vehicle at our shop, or use this
        website and our social media channels. By booking or buying from us, you agree to them.
      </p>

      <h2>Quotes and prices</h2>
      <ul>
        <li>
          Prices depend on your car make, model and variant, and on the product you choose. Prices shared in messages,
          comments or on social media are estimates until we confirm them for your vehicle.
        </li>
        <li>A quote is valid for the period stated on it, or 7 days if no period is stated.</li>
        <li>Offers are valid only for the dates and conditions stated in the offer, and cannot be combined unless stated.</li>
      </ul>

      <h2>Bookings and appointments</h2>
      <ul>
        <li>Please arrive on time for your appointment. If you are late, we may need to move your booking.</li>
        <li>
          Some services, such as PPF, ceramic coating and detailing, may need your vehicle for a full day or more. We will
          tell you the expected time when you book.
        </li>
        <li>
          If an advance payment is taken for a booking, the conditions for refunds or changes will be told to you at the
          time of booking.
        </li>
      </ul>

      <h2>Your vehicle</h2>
      <ul>
        <li>Remove valuables from your vehicle before handing it over. We are not responsible for items left inside.</li>
        <li>
          Before work starts, we check the vehicle with you. Please point out any existing damage, warning lights or
          electrical problems so they can be noted.
        </li>
        <li>
          Please check the work and the vehicle when you collect it. Tell us straight away if anything is not right, so
          we can look at it while you are at the shop.
        </li>
      </ul>

      <h2>Fitting and electrical work</h2>
      <ul>
        <li>
          Fitting lights, audio, screens, dashcams and other electrical accessories involves the vehicle&rsquo;s wiring.
          If you notice any electrical issue after fitting, contact us as soon as possible and do not let anyone else
          change the wiring first, so that we can inspect and fix it.
        </li>
        <li>
          Some accessories and modifications may affect the vehicle manufacturer&rsquo;s warranty or must follow motor
          vehicle rules. Please check with your dealer if you are unsure. We will tell you if we know a product is not
          road legal.
        </li>
        <li>
          We may decline to fit parts supplied by the customer, because we cannot check their quality or offer a warranty
          on them.
        </li>
      </ul>

      <h2>Warranty</h2>
      <ul>
        <li>
          Products carry the manufacturer&rsquo;s warranty, where one is offered. The warranty period and conditions are
          shown on your invoice or the product&rsquo;s warranty card.
        </li>
        <li>
          Warranty does not cover damage from accidents, misuse, washing with harsh chemicals, or repairs and changes made
          by others.
        </li>
        <li>Keep your invoice. It is needed for any warranty claim.</li>
      </ul>

      <h2>Payment</h2>
      <p>
        Payment is due when you collect your vehicle or receive your products, unless agreed otherwise in writing. We
        accept the payment methods shown at the shop.
      </p>

      <h2>Returns</h2>
      <p>
        Products that have been fitted, cut to size or made for your vehicle, such as seat covers and films, cannot be
        returned unless they are faulty. Unused products in their original packaging may be exchanged within 7 days with
        the invoice, at our discretion.
      </p>

      <h2>Liability</h2>
      <p>
        We take care with every vehicle and product. Our responsibility for any claim is limited to repairing or
        replacing the work or product concerned, or refunding the amount you paid for it. This does not limit any rights
        you have under the Consumer Protection Act, 2019.
      </p>

      <h2>Website and social media</h2>
      <p>
        Photos and videos on this website and our social media show work done for our customers and are for
        illustration. Products, colours and finishes may vary by vehicle. You may not copy our photos, videos or logo
        without our permission.
      </p>

      <h2>Governing law</h2>
      <p>These terms are governed by the laws of India. Disputes are subject to the courts in Pune, Maharashtra.</p>

      <h2>Contact us</h2>
      <ContactBox />
    </LegalShell>
  );
}
