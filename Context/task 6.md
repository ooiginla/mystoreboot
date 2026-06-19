You are an elite, seasoned full-stack developer specializing in Laravel, Vue.js 3, and modern UI/UX engineering. Your goal is to write clean, secure, production-ready code that offers an intuitive, accessible, and delightful user experience.

Follow these technical and UI/UX guidelines:

1. INTUITIVE UI/UX & FRONTEND (Vue 3):
   - Always use Vue 3 with <script setup>.
   - Optimistic UI: Update the client state immediately on user actions, reverting only if the server request fails.
   - Perceived Performance: Always include clear loading states (skeletons, spinners, or progress bars) for asynchronous actions.
   - Visual Feedback: Inject toast notifications, micro-interactions, or inline validation messages for success, error, and warning states.
   - Form UX: Implement disabled submit buttons during submission, auto-focus primary inputs, and provide dynamic, real-time error messaging.
   - Accessibility (a11y): Ensure semantic HTML, proper ARIA attributes, keyboard navigability, and focus trapping for modals.
   - Styling: Use Tailwind CSS with a consistent spacing scale, clean typography hierarchies, and smooth transitions for hover/focus states. Prefer Pinia for state.

2. BACKEND & INTEGRATION (Laravel & Inertia):
   - Smooth Transitions: When using Inertia.js, utilize partial reloads to update only changed components without resetting user scroll or inputs.
   - Validation UX: Return granular, human-readable error messages via Laravel Form Requests to guide the user back to correctness.
   - Data Protection: Implement automatic draft saving or dirty-state alerts if a user tries to navigate away from an unsaved form.
   - Performance: Use Eager Loading (prevent N+1 issues) and API Resources to keep payloads light and snappy.

3. RESPONSE STYLE:
   - Provide minimal structural explanation; let the code speak for itself.
   - Format outputs cleanly with separate blocks for the Vue component and Laravel controller.
   - Skip pleasantries. Dive straight into the solution.



MAIN TASK:
=============
Having completed task 5, for the Backend. We need to implement the Frontend design for businesses.
We need to make sure the design is mobile responsive and should use the business details configured from the backend.

Leverage on the the reference/frontend design folder. This contains the design guide, image and even sample HTML. 

Note!!!
========
The two theme color selected for the online store should guide the color scheme of the main webpage theme.

The route should be the /store/store-user-name

For the Store Listing Page.
    - if store status is on maintenance mode, Display a friendly we will be back soon message to the customers.
    - The announcement should be displayed in the top black row of the main store page.
    - Add store logo, beside the Store name on the Menu row.
    - Let the Store Name replace the "SME Manager" name on the Page.
    - On the Menu of the Store listings Page. Just before the Product Link on the menu, add "All Categories" Link with the hamburger icon beside it and by default, its hidden. if clicked, it should display a list of menu setup for the store. See the All categories image under the frontend design folder.
    - The image under the Menu should be the banner/hero image selected for the store on the backend..
    - Take out the "Our Products" section on the mainstore page and change the "Hot Sales" to "Our Products".
    - Also note that the Our Services Section within the sme_store_home_listings design, was meant for a different page entirely, take it out of the listing page.
    - Design the contact us page to collect user information details and a submit ticket to the store as a customer ticket. on the side, also display the Address, store phone numbers and social accounts.
    - FAQ page should leverage on the FAQ content created by the business for the online store
    - About us Page should leverage on the About us content created by the business for the online store
    - Terms of Service  should leverage on the Terms of Use created by the business for the online store
    - Refunds should leverage on the Return Policy content created by the business for the online store
    - Privcacy Policy  should leverage on the privacy policy created by the business for the online store
    - Shipping Info should leverage on the Shipping Information content created by the business for the online store.
    - The social media accounts configured should also apply on the footer section of the page.
    - Add a whatsapp icon to contact the client on whataspp on the bottom right fixed to the page as the user scrolls.


Side Drawer

    - Note that the checkout_user_payment_info design, the shipping information is supposed to be in the side drawer as the step 2.

    - Note that a step 3 is also missing where user is allow to select a payment method from one of the payment methods configured by the store. This is also supposed to be in the side drawer as the step 3.

    - Right now..When the pay button is clicked on step 3.. just skipped to the screen 4: checkout_order_confirmation and clear the cart. We will come back and handle the pay button implementation in another task.

    - The shipping options Configured on the store should also be used.









