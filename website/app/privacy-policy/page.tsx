import type { Metadata } from "next";
import LegalShell, { ContactBox } from "../components/LegalShell";
import { site } from "@/lib/site";

export const metadata: Metadata = {
  title: "Privacy policy",
  description: `How ${site.name} collects, uses and protects your personal information.`,
};

export default function PrivacyPolicy() {
  return (
    <LegalShell title="Privacy policy" current="privacy">
      <p>
        This policy explains what personal information {site.name} (&ldquo;we&rdquo;, &ldquo;us&rdquo;) collects when
        you visit our shop, use this website, or contact us on WhatsApp, Instagram, Facebook, Messenger, Google or by
        phone, and how we use it. It is written to follow India&rsquo;s Digital Personal Data Protection Act, 2023.
      </p>

      <h2>Information we collect</h2>
      <ul>
        <li>
          <strong>Contact details</strong> you share with us: your name, phone number, WhatsApp number, and social media
          username.
        </li>
        <li>
          <strong>Enquiry details</strong>: your car make and model, the products or services you ask about, photos you
          send, appointment dates, and quotes we give you.
        </li>
        <li>
          <strong>Messages and comments</strong> you send to our Instagram account, Facebook Page, Messenger or WhatsApp
          number, including comments on our posts.
        </li>
        <li>
          <strong>Purchase and job records</strong>: invoices, payments, and the work done on your vehicle.
        </li>
        <li>
          <strong>Website data</strong>: basic technical information your browser sends, such as device type and pages
          visited. This website does not use advertising cookies.
        </li>
      </ul>

      <h2>How we use it</h2>
      <ul>
        <li>To reply to your messages and comments, send quotes, product photos and prices, and book appointments.</li>
        <li>To follow up on your enquiry, by phone or message, until it is resolved or you ask us to stop.</li>
        <li>To carry out work on your vehicle, prepare invoices, and handle warranty claims and complaints.</li>
        <li>
          To send offers and updates, only if you have agreed to receive them. Every marketing message lets you opt out,
          for example by replying STOP.
        </li>
        <li>To improve our service and keep records required by law, such as tax records.</li>
      </ul>

      <h2>Automated replies</h2>
      <p>
        When you comment on our posts or message our Instagram account, Facebook Page or WhatsApp number, you may first
        receive an automatic reply with information such as prices, shop timings or our location. These replies are sent
        through the official tools provided by Meta Platforms. A member of our team can read the conversation and reply
        personally. Automated replies are not used to make decisions that have legal or similar effects on you.
      </p>

      <h2>Who we share it with</h2>
      <p>We do not sell your personal information. We share it only with:</p>
      <ul>
        <li>
          <strong>Platforms you contact us through</strong>: Meta Platforms (Instagram, Facebook, Messenger, WhatsApp)
          and Google, which process messages under their own privacy policies.
        </li>
        <li>
          <strong>Service providers</strong> who help us run our business, such as our marketing agency (Marketix
          Studio, which manages our online channels on our behalf), hosting, and payment providers. They may use your
          information only to provide services to us.
        </li>
        <li>
          <strong>Product manufacturers</strong>, when needed to process a warranty claim for a product fitted to your
          vehicle.
        </li>
        <li>Authorities, when the law requires it.</li>
      </ul>

      <h2>How long we keep it</h2>
      <p>
        We keep enquiry and message records for as long as needed to answer and follow up on your enquiry, and job and
        invoice records for as long as tax and warranty rules require. Information we no longer need is deleted.
      </p>

      <h2>Your rights</h2>
      <p>You can ask us to:</p>
      <ul>
        <li>tell you what personal information we hold about you;</li>
        <li>correct or update it;</li>
        <li>delete it, unless we must keep it by law;</li>
        <li>stop sending you marketing messages, at any time.</li>
      </ul>

      <h2>How to delete your data</h2>
      <p>
        To have your information deleted, including data received through our Instagram, Facebook or WhatsApp
        automation, send a WhatsApp message to {site.phoneDisplay} with the words &ldquo;Delete my data&rdquo; and the
        phone number or social media username you contacted us from, or tell us in person at the shop. We will confirm
        and complete the request within 30 days, except for records we must keep by law, which we will tell you about.
      </p>
      <p>
        You can also remove our app&rsquo;s access from your own account at any time: on Facebook, go to Settings &amp;
        privacy, then Settings, then Business integrations, and remove the app.
      </p>

      <h2>Security</h2>
      <p>
        We limit access to your information to the people who need it to serve you, and use password-protected accounts
        on the services we use. No method of storing or sending information is completely secure, but we take reasonable
        steps to protect it.
      </p>

      <h2>Children</h2>
      <p>Our services are meant for adults. We do not knowingly collect information from anyone under 18.</p>

      <h2>Changes to this policy</h2>
      <p>
        If we change this policy, we will update the date at the top of this page. Important changes will also be shown
        on this website.
      </p>

      <h2>Contact us</h2>
      <p>For questions or requests about your personal information, contact us:</p>
      <ContactBox />
    </LegalShell>
  );
}
