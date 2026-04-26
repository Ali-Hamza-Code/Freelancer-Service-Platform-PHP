<?php
/**
 * Service Class
 * Object-Oriented representation of a service for cart functionality
 */

class Service
{
    // Private properties
    private $serviceId;
    private $freelancerId;
    private $title;
    private $category;
    private $subcategory;
    private $description;
    private $price;
    private $deliveryTime;
    private $revisionsIncluded;
    private $image1;
    private $image2;
    private $image3;
    private $status;
    private $featuredStatus;
    private $createdDate;
    private $freelancerName;
    private $addedToCartAt;

    /**
     * Constructor
     */
    public function __construct($data)
    {
        $this->serviceId = $data['service_id'] ?? '';
        $this->freelancerId = $data['freelancer_id'] ?? '';
        $this->title = $data['title'] ?? '';
        $this->category = $data['category'] ?? '';
        $this->subcategory = $data['subcategory'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->price = (float) ($data['price'] ?? 0);
        $this->deliveryTime = (int) ($data['delivery_time'] ?? 0);
        $this->revisionsIncluded = (int) ($data['revisions_included'] ?? 0);
        $this->image1 = $data['image_1'] ?? '';
        $this->image2 = $data['image_2'] ?? null;
        $this->image3 = $data['image_3'] ?? null;
        $this->status = $data['status'] ?? 'Active';
        $this->featuredStatus = $data['featured_status'] ?? 'No';
        $this->createdDate = $data['created_date'] ?? date('Y-m-d H:i:s');
        $this->freelancerName = $data['freelancer_name'] ?? '';
        $this->addedToCartAt = time();
    }

    // Getters
    public function getServiceId()
    {
        return $this->serviceId;
    }

    public function getFreelancerId()
    {
        return $this->freelancerId;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getSubcategory()
    {
        return $this->subcategory;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getDeliveryTime()
    {
        return $this->deliveryTime;
    }

    public function getRevisionsIncluded()
    {
        return $this->revisionsIncluded;
    }

    public function getImage1()
    {
        return $this->image1;
    }

    public function getImage2()
    {
        return $this->image2;
    }

    public function getImage3()
    {
        return $this->image3;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getFeaturedStatus()
    {
        return $this->featuredStatus;
    }

    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    public function getFreelancerName()
    {
        return $this->freelancerName;
    }

    public function getAddedToCartAt()
    {
        return $this->addedToCartAt;
    }

    /**
     * Get formatted price (e.g., "$150.00")
     * @return string
     */
    public function getFormattedPrice()
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get formatted delivery time (e.g., "5 days")
     * @return string
     */
    public function getFormattedDelivery()
    {
        $days = $this->deliveryTime;
        return $days . ' ' . ($days === 1 ? 'day' : 'days');
    }

    /**
     * Get formatted revisions
     * @return string
     */
    public function getFormattedRevisions()
    {
        if ($this->revisionsIncluded >= 999) {
            return 'Unlimited';
        }
        return (string) $this->revisionsIncluded;
    }

    /**
     * Calculate service fee (5% of price)
     * @return float
     */
    public function calculateServiceFee()
    {
        return $this->price * 0.05;
    }

    /**
     * Get total with fee (price + 5% service fee)
     * @return float
     */
    public function getTotalWithFee()
    {
        return $this->price + $this->calculateServiceFee();
    }

    /**
     * Get formatted service fee
     * @return string
     */
    public function getFormattedServiceFee()
    {
        return '$' . number_format($this->calculateServiceFee(), 2);
    }

    /**
     * Get formatted total with fee
     * @return string
     */
    public function getFormattedTotalWithFee()
    {
        return '$' . number_format($this->getTotalWithFee(), 2);
    }

    /**
     * Get main image path
     * @return string
     */
    public function getMainImage()
    {
        return $this->image1;
    }

    /**
     * Get all images as array
     * @return array
     */
    public function getAllImages()
    {
        $images = [$this->image1];
        if ($this->image2)
            $images[] = $this->image2;
        if ($this->image3)
            $images[] = $this->image3;
        return $images;
    }

    /**
     * Check if service is featured
     * @return bool
     */
    public function isFeatured()
    {
        return $this->featuredStatus === 'Yes';
    }

    /**
     * Check if service is active
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'Active';
    }

    /**
     * Convert to array
     * @return array
     */
    public function toArray()
    {
        return [
            'service_id' => $this->serviceId,
            'freelancer_id' => $this->freelancerId,
            'title' => $this->title,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'description' => $this->description,
            'price' => $this->price,
            'delivery_time' => $this->deliveryTime,
            'revisions_included' => $this->revisionsIncluded,
            'image_1' => $this->image1,
            'image_2' => $this->image2,
            'image_3' => $this->image3,
            'status' => $this->status,
            'featured_status' => $this->featuredStatus,
            'created_date' => $this->createdDate,
            'freelancer_name' => $this->freelancerName,
            'added_to_cart_at' => $this->addedToCartAt
        ];
    }
}
?>