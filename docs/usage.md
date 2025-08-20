# Items API Documentation

This service provides two collections of items — **fruits** and **vegetables** — with CRUD-like operations and filtering options.  
It is built on **Symfony 7** using PHP 8 attributes and follows SOLID, KISS, DRY principles.

---

## Dataset Bootstrapping

- On the **first HTTP request**, the application automatically imports the initial dataset from `request.json`.
- All quantities are **normalized to grams** internally (values in the file may be `g` or `kg`).

> **Note on IDs:** In the current in-memory storage, adding an item with an existing `id` **overwrites** the previous entry for that collection.

---

## Base URL

```
/api/{type}
```

Where `{type}` is either:

- `fruit`
- `vegetable`

---

## Endpoints

### 1) List Items

**GET** `/api/fruit`  
**GET** `/api/vegetable`

**Query Parameters (filters)**

| Name   | Type   | Default | Description                                  |
|--------|--------|---------|----------------------------------------------|
| `q`    | string | —       | Case-insensitive substring match on `name`.  |
| `min`  | int    | —       | Minimum quantity **in grams**.               |
| `max`  | int    | —       | Maximum quantity **in grams**.               |
| `unit` | enum   | `g`     | Output unit: `g` or `kg`.                    |

**Example request**
```
GET /api/fruit?unit=kg&q=ber&min=500
```

**Example response**
```json
[
  { "id": 8, "name": "Berries", "type": "fruit", "quantity": 10, "unit": "kg" }
]
```

**cURL**
```bash
curl -X GET "http://localhost:8000/api/fruit?unit=kg&q=ber&min=500" -H "Accept: application/json"
```

---

### 2) Add Item

**POST** `/api/fruit`  
**POST** `/api/vegetable`

**Request Body**
```json
{
  "id": 21,
  "name": "Spinach",
  "type": "vegetable",
  "quantity": 5,
  "unit": "kg"
}
```

**Response (success)**
```json
{ "status": "ok" }
```
HTTP `201 Created`

**cURL**
```bash
curl -X POST "http://localhost:8000/api/vegetable" -H "Content-Type: application/json"      -d '{ "id": 21, "name": "Spinach", "type": "vegetable", "quantity": 5, "unit": "kg" }'
```

---

## More cURL Examples

**All vegetables in kilograms**
```bash
curl -X GET "http://localhost:8000/api/vegetable?unit=kg" -H "Accept: application/json"
```

**Search fruits containing “apple”**
```bash
curl -X GET "http://localhost:8000/api/fruit?q=apple" -H "Accept: application/json"
```

**Fruits with at least 5 kg**
```bash
curl -X GET "http://localhost:8000/api/fruit?min=5000&unit=kg" -H "Accept: application/json"
```

**Add a new fruit (grams input)**
```bash
curl -X POST "http://localhost:8000/api/fruit" -H "Content-Type: application/json" -d '{"id": 22, "name": "Mango", "type": "fruit", "quantity": 1200, "unit": "g"}'
```

---

## Technical Notes

- **Units:** Internally stored in grams; output unit controlled by `unit` query param.
- **Validation:** Required fields, valid `type`/`unit`, non-negative `quantity`, non-empty `name`. Body must be valid JSON.
- **Storage Engine:** In-memory (`FruitCollection`, `VegetableCollection`). Easily swappable for a DB later.
- **Design:** Clean domain model; controllers delegate to services; input validation centralized; no business logic in controllers.
- **Routing:** PHP 8 attributes (`#[Route(...)]`). Ensure route import uses `type: attribute`.

---

## Error Handling & Examples

### 404 – Unknown collection
Requested `{type}` is neither `fruit` nor `vegetable`.

**Example**
```bash
curl -i -X GET "http://localhost:8000/api/meat"
```
**Response**
```json
{ "error": "Unknown collection" }
```

---

### 400 – Invalid JSON payload
Body is not valid JSON.

**Example**
```bash
curl -i -X POST "http://localhost:8000/api/fruit" -H "Content-Type: application/json" -d 'not a json'
```
**Response**
```json
{ "error": "Invalid JSON" }
```

---

### 400 – Validation error: missing field
A required field is missing (`id`, `name`, `type`, `quantity`, `unit`).

**Example**
```bash
curl -i -X POST "http://localhost:8000/api/fruit" -H "Content-Type: application/json" -d '{ "id": 1, "type": "fruit", "quantity": 2, "unit": "kg" }'
```
**Response**
```json
{ "error": "Missing key 'name'" }
```

---

### 400 – Validation error: invalid unit
`unit` must be `g` or `kg`.

**Example**
```bash
curl -i -X POST "http://localhost:8000/api/fruit" -H "Content-Type: application/json" -d '{ "id": 3, "name": "Test", "type": "fruit", "quantity": 10, "unit": "lb" }'
```
**Response**
```json
{ "error": "Invalid unit 'lb'" }
```

---

### 400 – Validation error: invalid type
`type` must be `fruit` or `vegetable`, and must match the endpoint.

**Example (type mismatch)**
```bash
curl -i -X POST "http://localhost:8000/api/fruit" -H "Content-Type: application/json" -d '{ "id": 7, "name": "Carrot", "type": "vegetable", "quantity": 1, "unit": "kg" }'
```
**Response**
```json
{ "error": "Only fruits allowed." }
```

---

### 400 – Validation error: negative quantity
`quantity` cannot be negative.

**Example**
```bash
curl -i -X POST "http://localhost:8000/api/vegetable" -H "Content-Type: application/json" -d '{ "id": 6, "name": "Broccoli", "type": "vegetable", "quantity": -1, "unit": "kg" }'
```
**Response**
```json
{ "error": "Negative quantity not allowed" }
```

---

### 400 – Validation error: non-positive or invalid id
`id` must be a positive integer.

**Example**
```bash
curl -i -X POST "http://localhost:8000/api/fruit" -H "Content-Type: application/json" -d '{ "id": 0, "name": "Kiwi", "type": "fruit", "quantity": 1, "unit": "kg" }'
```
**Response**
```json
{ "error": "Invalid id '0'" }
```

---

## Quick Test (local server)

Start the Symfony dev server:
```bash
symfony server:start -d
# or
php -S localhost:8000 -t public
```

Then run:
```bash
curl -s "http://localhost:8000/api/vegetable?unit=kg"
```

---
