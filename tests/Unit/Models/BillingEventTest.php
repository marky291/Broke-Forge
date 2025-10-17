<?php

namespace Tests\Unit\Models;

use App\Models\BillingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test billing event belongs to a user.
     */
    public function test_belongs_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $billingEvent = BillingEvent::factory()->create([
            'user_id' => $user->id,
        ]);

        // Act
        $result = $billingEvent->user;

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    /**
     * Test metadata is cast to array.
     */
    public function test_metadata_is_cast_to_array(): void
    {
        // Arrange
        $metadata = [
            'amount' => 2500,
            'currency' => 'usd',
            'customer' => 'cus_123456',
        ];
        $billingEvent = BillingEvent::factory()->create([
            'metadata' => $metadata,
        ]);

        // Act
        $result = $billingEvent->metadata;

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($metadata, $result);
    }

    /**
     * Test metadata can be empty array.
     */
    public function test_metadata_can_be_empty_array(): void
    {
        // Arrange
        $billingEvent = BillingEvent::factory()->create([
            'metadata' => [],
        ]);

        // Act
        $metadata = $billingEvent->metadata;

        // Assert
        $this->assertIsArray($metadata);
        $this->assertEmpty($metadata);
    }

    /**
     * Test create from stripe event creates billing event.
     */
    public function test_create_from_stripe_event_creates_billing_event(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Mock Stripe event object
        $stripeEvent = (object) [
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'amount' => 5000,
                    'currency' => 'usd',
                ],
            ],
        ];

        // Add toArray method to data object
        $stripeEvent->data = new class($stripeEvent->data)
        {
            private $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function toArray()
            {
                return [
                    'object' => [
                        'amount' => 5000,
                        'currency' => 'usd',
                    ],
                ];
            }

            public function __get($key)
            {
                return $this->data->$key;
            }
        };

        // Act
        $billingEvent = BillingEvent::createFromStripeEvent($user, $stripeEvent);

        // Assert
        $this->assertInstanceOf(BillingEvent::class, $billingEvent);
        $this->assertEquals($user->id, $billingEvent->user_id);
        $this->assertEquals('payment_intent.succeeded', $billingEvent->type);
        $this->assertEquals('evt_test_123', $billingEvent->stripe_event_id);
        $this->assertIsArray($billingEvent->metadata);
        $this->assertNull($billingEvent->description);
    }

    /**
     * Test create from stripe event with description.
     */
    public function test_create_from_stripe_event_with_description(): void
    {
        // Arrange
        $user = User::factory()->create();
        $description = 'Payment received for subscription';

        $stripeEvent = (object) [
            'id' => 'evt_test_456',
            'type' => 'invoice.payment_succeeded',
            'data' => (object) [],
        ];

        $stripeEvent->data = new class
        {
            public function toArray()
            {
                return ['invoice_id' => 'in_123'];
            }
        };

        // Act
        $billingEvent = BillingEvent::createFromStripeEvent($user, $stripeEvent, $description);

        // Assert
        $this->assertEquals($description, $billingEvent->description);
    }

    /**
     * Test create from stripe event stores metadata correctly.
     */
    public function test_create_from_stripe_event_stores_metadata(): void
    {
        // Arrange
        $user = User::factory()->create();

        $stripeEvent = (object) [
            'id' => 'evt_test_789',
            'type' => 'customer.subscription.created',
            'data' => (object) [],
        ];

        $metadata = [
            'subscription_id' => 'sub_123',
            'plan' => 'professional',
            'quantity' => 1,
        ];

        $stripeEvent->data = new class($metadata)
        {
            private $metadata;

            public function __construct($metadata)
            {
                $this->metadata = $metadata;
            }

            public function toArray()
            {
                return $this->metadata;
            }
        };

        // Act
        $billingEvent = BillingEvent::createFromStripeEvent($user, $stripeEvent);

        // Assert
        $this->assertEquals($metadata, $billingEvent->metadata);
        $this->assertDatabaseHas('billing_events', [
            'user_id' => $user->id,
            'stripe_event_id' => 'evt_test_789',
            'type' => 'customer.subscription.created',
        ]);
    }

    /**
     * Test factory creates billing event with correct attributes.
     */
    public function test_factory_creates_billing_event_with_correct_attributes(): void
    {
        // Act
        $billingEvent = BillingEvent::factory()->create();

        // Assert
        $this->assertNotNull($billingEvent->user_id);
        $this->assertNotNull($billingEvent->type);
        $this->assertNotNull($billingEvent->stripe_event_id);
        $this->assertIsArray($billingEvent->metadata);
    }

    /**
     * Test factory payment succeeded state.
     */
    public function test_factory_payment_succeeded_state(): void
    {
        // Act
        $billingEvent = BillingEvent::factory()->paymentSucceeded()->create();

        // Assert
        $this->assertEquals('payment_intent.succeeded', $billingEvent->type);
        $this->assertEquals('Payment succeeded', $billingEvent->description);
    }

    /**
     * Test factory subscription created state.
     */
    public function test_factory_subscription_created_state(): void
    {
        // Act
        $billingEvent = BillingEvent::factory()->subscriptionCreated()->create();

        // Assert
        $this->assertEquals('customer.subscription.created', $billingEvent->type);
        $this->assertEquals('Subscription created', $billingEvent->description);
    }

    /**
     * Test factory no description state.
     */
    public function test_factory_no_description_state(): void
    {
        // Act
        $billingEvent = BillingEvent::factory()->noDescription()->create();

        // Assert
        $this->assertNull($billingEvent->description);
    }

    /**
     * Test billing event can store different event types.
     */
    public function test_can_store_different_event_types(): void
    {
        // Arrange
        $eventTypes = [
            'payment_intent.succeeded',
            'customer.subscription.created',
            'invoice.payment_succeeded',
            'charge.succeeded',
        ];

        // Act & Assert
        foreach ($eventTypes as $eventType) {
            $billingEvent = BillingEvent::factory()->create([
                'type' => $eventType,
            ]);

            $this->assertEquals($eventType, $billingEvent->type);
        }
    }

    /**
     * Test billing event can be created with all fillable attributes.
     */
    public function test_can_create_with_all_fillable_attributes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $attributes = [
            'user_id' => $user->id,
            'type' => 'payment_intent.succeeded',
            'stripe_event_id' => 'evt_custom_123',
            'metadata' => ['amount' => 1000],
            'description' => 'Test payment event',
        ];

        // Act
        $billingEvent = BillingEvent::create($attributes);

        // Assert - check database without metadata (stored as JSON)
        $this->assertDatabaseHas('billing_events', [
            'user_id' => $user->id,
            'type' => 'payment_intent.succeeded',
            'stripe_event_id' => 'evt_custom_123',
            'description' => 'Test payment event',
        ]);
        $this->assertEquals($attributes['type'], $billingEvent->type);
        $this->assertEquals($attributes['stripe_event_id'], $billingEvent->stripe_event_id);
        $this->assertEquals($attributes['metadata'], $billingEvent->metadata);
        $this->assertEquals($attributes['description'], $billingEvent->description);
    }

    /**
     * Test metadata persists complex data structures.
     */
    public function test_metadata_persists_complex_data_structures(): void
    {
        // Arrange
        $complexMetadata = [
            'payment' => [
                'amount' => 5000,
                'currency' => 'usd',
                'status' => 'succeeded',
            ],
            'customer' => [
                'id' => 'cus_123',
                'email' => 'test@example.com',
            ],
            'items' => [
                ['name' => 'Item 1', 'price' => 2000],
                ['name' => 'Item 2', 'price' => 3000],
            ],
        ];

        $billingEvent = BillingEvent::factory()->create([
            'metadata' => $complexMetadata,
        ]);

        // Act
        $billingEvent->refresh();

        // Assert
        $this->assertEquals($complexMetadata, $billingEvent->metadata);
        $this->assertEquals(5000, $billingEvent->metadata['payment']['amount']);
        $this->assertCount(2, $billingEvent->metadata['items']);
    }

    /**
     * Test stripe event id is unique identifier.
     */
    public function test_stripe_event_id_stores_correctly(): void
    {
        // Arrange
        $stripeEventId = 'evt_unique_identifier_12345';
        $billingEvent = BillingEvent::factory()->create([
            'stripe_event_id' => $stripeEventId,
        ]);

        // Act
        $storedId = $billingEvent->stripe_event_id;

        // Assert
        $this->assertEquals($stripeEventId, $storedId);
        $this->assertDatabaseHas('billing_events', [
            'stripe_event_id' => $stripeEventId,
        ]);
    }

    /**
     * Test description can be null.
     */
    public function test_description_can_be_null(): void
    {
        // Arrange
        $billingEvent = BillingEvent::factory()->create([
            'description' => null,
        ]);

        // Act
        $description = $billingEvent->description;

        // Assert
        $this->assertNull($description);
    }

    /**
     * Test description can be a long string.
     */
    public function test_description_can_be_long_string(): void
    {
        // Arrange
        $longDescription = 'This is a very long description that contains detailed information about the billing event including payment details, customer information, and other relevant data that might be important for record keeping.';

        $billingEvent = BillingEvent::factory()->create([
            'description' => $longDescription,
        ]);

        // Act
        $description = $billingEvent->description;

        // Assert
        $this->assertEquals($longDescription, $description);
    }
}
