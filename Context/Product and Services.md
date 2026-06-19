Product and Services

C. Product & Service Management

Storeboot should support businesses that sell physical products and businesses that offer services.

Features
Product creation
Service creation
Product categories
Product variants:
    Size
    Color
    Weight
    Model
SKU generation
Barcode support
Product images
Selling price
Cost price
Discount price
Tax settings
Product status:
    Active
    Out of stock
    Discontinued
Service price list
Product/service bundles



# Recommendation: Treat Variants as Sellable Items

For **Storeboot**, a product variant should not just be a visual choice or option. In a proper retail/SME system, each variant should be capable of having its own:

* Price
* Cost price
* SKU
* Barcode
* Stock quantity
* Reorder level
* Discount
* Tax behavior, if needed
* Image, optional

So the best advice is:

> Create the main product at a high level, but let each variant become its own sellable stock item when it affects price, stock, or identity.

---

# Simple Example

## Main Product

```text
Product Name: Nike T-Shirt
Category: Clothing
Brand: Nike
Description: Cotton round-neck T-shirt
```

## Variants

| Variant       |       SKU |  Price | Stock |
| ------------- | --------: | -----: | ----: |
| Red / Small   | NTS-RED-S | ₦8,000 |    15 |
| Red / Medium  | NTS-RED-M | ₦8,500 |    10 |
| Blue / Small  | NTS-BLU-S | ₦8,000 |     8 |
| Blue / Medium | NTS-BLU-M | ₦8,500 |    12 |

Here, the **main product** is “Nike T-Shirt”, but the actual things being sold are the variants.

---

# Why Each Variant Should Have Its Own Pricing

Because in real business, variants often behave differently.

## 1. Different sizes may have different prices

Example:

| Product  | Variant |   Price |
| -------- | ------- | ------: |
| Rice Bag | 5kg     |  ₦8,000 |
| Rice Bag | 10kg    | ₦15,000 |
| Rice Bag | 25kg    | ₦35,000 |

The 5kg, 10kg, and 25kg options are not just choices. They are different sellable items.

---

## 2. Different colors may have different demand

Example:

| Product  | Variant         | Stock |
| -------- | --------------- | ----: |
| Sneakers | Black / Size 42 |    20 |
| Sneakers | White / Size 42 |     5 |

Even if the price is the same, the stock is different. So each variant needs its own inventory record.

---

## 3. Different variants may come from different suppliers

Example:

| Product      | Variant | Supplier |
| ------------ | ------- | -------- |
| Office Chair | Black   | Vendor A |
| Office Chair | Brown   | Vendor B |

This affects purchase orders and vendor analytics.

---

## 4. Different variants may have different cost prices

Example:

| Product    | Variant           | Cost Price | Selling Price |
| ---------- | ----------------- | ---------: | ------------: |
| Phone Case | iPhone 13         |     ₦1,500 |        ₦3,000 |
| Phone Case | iPhone 15 Pro Max |     ₦2,500 |        ₦5,000 |

If the cost differs, then profit calculation must also differ.

---

# Best Data Design Approach

Use this structure:

## 1. Products Table

This stores the general product information.

```text
products
- id
- organization_id
- category_id
- name
- description
- brand
- product_type
- base_price
- base_cost_price
- has_variants
- status
```

The product table represents the **parent product**.

Example:

```text
Nike T-Shirt
```

---

## 2. Product Options Table

This stores option types like size, color, weight, material, etc.

```text
product_options
- id
- product_id
- name
```

Examples:

```text
Size
Color
Weight
Material
```

---

## 3. Product Option Values Table

This stores the possible values under each option.

```text
product_option_values
- id
- product_option_id
- value
```

Examples:

```text
Small
Medium
Large
Red
Blue
Black
5kg
10kg
25kg
```

---

## 4. Product Variants Table

This stores the actual sellable variant.

```text
product_variants
- id
- product_id
- sku
- barcode
- variant_name
- selling_price
- cost_price
- compare_at_price
- stock_quantity
- reorder_level
- image_url
- status
```

Examples:

```text
Red / Small
Red / Medium
Blue / Small
Blue / Medium
```

---

## 5. Product Variant Values Table

This connects a variant to its selected options.

```text
product_variant_values
- id
- product_variant_id
- product_option_value_id
```

Example:

```text
Variant: Red / Small
Values:
- Red
- Small
```

---

# Should Price Be Stored on Product or Variant?

## Best Rule

| Scenario                                   | Where Price Should Be Stored                                     |
| ------------------------------------------ | ---------------------------------------------------------------- |
| Product has no variants                    | Store price on product                                           |
| Product has variants with same price       | Store default price on product, copy to variants                 |
| Product has variants with different prices | Store actual price on each variant                               |
| Product has inventory tracking             | Track inventory per variant                                      |
| Product is only a service                  | Store price on service/product level unless service has packages |

---

# My Strong Recommendation for Storeboot

Use this rule:

> The main product can have a default/base price, but the final selling price should come from the variant if variants exist.

So:

```text
If product has variants:
    use variant price
else:
    use product base price
```

This gives you flexibility without making the system too complicated.

---

# Practical Example

## Product

```text
Name: Data Bundle
Base Price: ₦1,000
Has Variants: Yes
```

## Variants

| Variant |  Price |
| ------- | -----: |
| 1GB     | ₦1,000 |
| 2GB     | ₦1,800 |
| 5GB     | ₦4,000 |
| 10GB    | ₦7,500 |

The customer does not really buy “Data Bundle” directly. They buy a specific variant like **5GB**.

---

# Another Example: Same Price Variants

## Product

```text
Name: Polo Shirt
Base Price: ₦10,000
Has Variants: Yes
```

## Variants

| Variant        |   Price |
| -------------- | ------: |
| Black / Small  | ₦10,000 |
| Black / Medium | ₦10,000 |
| White / Small  | ₦10,000 |
| White / Medium | ₦10,000 |

Even though the price is the same, each variant should still exist because stock differs.

---

# Important Advice

Do **not** treat variants as just dropdown options if you want Storeboot to handle real inventory.

That approach becomes weak quickly.

For example, this is not enough:

```text
Product: Shoe
Options: Size 40, 41, 42
Price: ₦25,000
Stock: 100
```

Because you will not know:

* How many Size 40 are left
* How many Size 41 were sold
* Which size is fast-moving
* Which size should be reordered
* Which size is profitable
* Which size should be transferred to another branch

For a decision-support platform, variant-level data is very important.

---

# Recommended Storeboot Product Model

## Product Level

Use this for shared information:

* Product name
* Description
* Category
* Brand
* Images
* Default/base price
* Default cost price
* Tax category
* Unit of measurement
* Product type
* Has variants
* Status

## Variant Level

Use this for sellable/inventory-specific information:

* SKU
* Barcode
* Variant name
* Option combination
* Selling price
* Cost price
* Stock quantity
* Reorder level
* Branch stock
* Supplier/vendor
* Weight
* Status

---

# What About Services?

For services, variants can be treated as **service packages**.

Example:

## Main Service

```text
Service: Laundry
```

## Service Variants / Packages

| Package           |  Price |
| ----------------- | -----: |
| Shirt Washing     | ₦1,000 |
| Suit Dry Cleaning | ₦5,000 |
| Bedsheet Washing  | ₦3,000 |

Another example:

```text
Service: Website Design
```

| Package            |    Price |
| ------------------ | -------: |
| Basic Website      | ₦150,000 |
| Business Website   | ₦300,000 |
| E-commerce Website | ₦600,000 |

So yes, services can also have variants/packages.

---

# Final Recommendation

For Storeboot, design it this way:

## Product

Represents the general item.

Example:

```text
Nike T-Shirt
```

## Variant

Represents the actual sellable version.

Example:

```text
Nike T-Shirt - Red - Medium
```

## Price

The main product can have a **base price**, but the variant should have its own **actual selling price**.

## Inventory

Inventory should be tracked at the **variant level**, not just the product level.

## Sales

Sales should reference the **variant ID** where variants exist.

## Analytics

Analytics should support both:

* Product-level performance
* Variant-level performance

This is the best structure for a real SaaS ERP and decision-support platform.
